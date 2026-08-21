<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Web;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 本地版网页抓取工具。
 *
 * 使用 Laravel HTTP 客户端抓取指定 URL，将 HTML 解析为纯文本返回给模型。
 * 与 provider 原生 WebFetch 不同，本工具完全在本地执行，不依赖 AI 厂商的联网能力。
 */
class WebFetch implements Tool
{
    /**
     * 单次抓取最大返回字符数，超出截断。
     */
    protected const int MAX_CONTENT_LENGTH = 8000;

    /**
     * 请求超时时间（秒）。
     */
    protected const int TIMEOUT = 15;

    /**
     * 允许抓取的协议白名单。
     */
    protected const array ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '抓取指定 URL 的网页内容并转换为纯文本返回。适用于查阅在线文档、新闻、博客、公告等公开网页。仅支持 http/https 协议，会自动提取标题与正文，忽略脚本、样式和导航元素。只读操作，不会向目标网站提交任何数据。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()
                ->required()
                ->format('uri')
                ->max(2000)
                ->description('要抓取的完整网页 URL，必须以 http:// 或 https:// 开头'),
            'selector' => $schema->string()
                ->max(200)
                ->description('可选的 CSS 选择器，仅提取匹配元素内的内容（例如 article、.content、#main）。不传则自动提取 body 正文'),
        ];
    }

    /**
     * 执行抓取。
     */
    public function handle(Request $request): Stringable|string
    {
        $url = trim($request->string('url')->toString());
        $selector = trim($request->string('selector')->toString());

        if (! $this->isValidUrl($url)) {
            return '错误：URL 格式无效，必须是 http:// 或 https:// 开头的合法网址。';
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; AiAssistant/1.0; +https://www.larva.com.cn/bot)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                ])
                ->get($url);
        } catch (ConnectionException $e) {
            return "抓取失败：无法连接到目标网站（{$e->getMessage()}）。";
        }

        if (! $response->successful()) {
            return "抓取失败：服务器返回 HTTP {$response->status()} {$response->reason()}。";
        }

        $contentType = strtolower($response->header('Content-Type') ?? '');
        if (! str_contains($contentType, 'html') && ! str_contains($contentType, 'xml')) {
            // 非 HTML 内容直接返回纯文本（JSON/纯文本等）
            return $this->buildResult($url, null, $this->truncate($response->body(), self::MAX_CONTENT_LENGTH));
        }

        $body = $response->body();
        if ($body === '') {
            return '抓取失败：响应内容为空。';
        }

        return $this->parseHtml($url, $body, $selector);
    }

    /**
     * 解析 HTML 并提取纯文本。
     */
    protected function parseHtml(string $url, string $html, string $selector): string
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // 强制 UTF-8 解析，避免中文乱码
        $html = '<?xml encoding="UTF-8">'.$html;
        $loaded = $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $this->buildResult($url, null, $this->truncate(strip_tags($html), self::MAX_CONTENT_LENGTH));
        }

        $xpath = new DOMXPath($dom);

        // 提取标题
        $title = null;
        $titleNodes = $xpath->query('//title');
        if ($titleNodes !== false && $titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
        }

        // 定位正文节点
        $contextNode = $this->resolveContextNode($dom, $xpath, $selector);

        // 移除无关标签
        foreach (['script', 'style', 'noscript', 'iframe', 'svg', 'nav', 'header', 'footer', 'aside', 'form', 'button'] as $tag) {
            $nodes = $contextNode->getElementsByTagName($tag);
            $toRemove = [];
            foreach ($nodes as $node) {
                $toRemove[] = $node;
            }
            foreach ($toRemove as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $text = $this->domToText($contextNode);
        $text = $this->normalizeWhitespace($text);

        return $this->buildResult($url, $title, $this->truncate($text, self::MAX_CONTENT_LENGTH));
    }

    /**
     * 根据 CSS 选择器（仅支持标签名 / #id / .class 简单形式）定位上下文节点。
     */
    protected function resolveContextNode(DOMDocument $dom, DOMXPath $xpath, string $selector): DOMNode
    {
        if ($selector === '') {
            $body = $dom->getElementsByTagName('body');
            if ($body->length > 0) {
                return $body->item(0);
            }

            return $dom->documentElement ?? $dom;
        }

        // tag
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $selector)) {
            $nodes = $dom->getElementsByTagName($selector);
            if ($nodes->length > 0) {
                return $nodes->item(0);
            }
        }

        // #id
        if (str_starts_with($selector, '#')) {
            $element = $dom->getElementById(substr($selector, 1));
            if ($element) {
                return $element;
            }
        }

        // .class
        if (str_starts_with($selector, '.')) {
            $className = substr($selector, 1);
            $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' {$className} ')]");
            if ($nodes !== false && $nodes->length > 0) {
                return $nodes->item(0);
            }
        }

        // 通用 XPath 回退：尝试把 CSS 当 XPath 执行
        $nodes = $xpath->query($selector);
        if ($nodes !== false && $nodes->length > 0) {
            return $nodes->item(0);
        }

        // 回退到 body
        $body = $dom->getElementsByTagName('body');

        return $body->length > 0 ? $body->item(0) : $dom->documentElement ?? $dom;
    }

    /**
     * 将 DOM 节点递归转换为带换行的纯文本。
     */
    protected function domToText(DOMNode $node): string
    {
        $text = '';
        if ($node->childNodes === null) {
            return $node->textContent ?? '';
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);
                $childText = $this->domToText($child);

                // 块级元素前后加换行
                if (in_array($tag, ['p', 'div', 'br', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'tr', 'article', 'section', 'blockquote', 'pre'], true)) {
                    $text = rtrim($text, "\n")."\n".$childText."\n";
                } else {
                    $text .= $childText;
                }
            }
        }

        return $text;
    }

    /**
     * 构造返回给模型的结果字符串。
     */
    protected function buildResult(string $url, ?string $title, string $content): string
    {
        $parts = ["URL: {$url}"];
        if ($title !== null && $title !== '') {
            $parts[] = "Title: {$title}";
        }
        $parts[] = 'Content:';
        $parts[] = $content;

        return implode("\n", $parts);
    }

    /**
     * 校验 URL 是否合法且协议在白名单内。
     */
    protected function isValidUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 2000) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 清理多余空白字符。
     */
    protected function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * 截断字符串并标记。
     */
    protected function truncate(string $text, int $max): string
    {
        if (Str::length($text) <= $max) {
            return $text;
        }

        return Str::substr($text, 0, $max)."\n\n[内容已截断，共 ".Str::length($text).' 字符，仅显示前 '.$max.' 字符]';
    }
}
