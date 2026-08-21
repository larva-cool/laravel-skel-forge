<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\System;

use App\Enums\AttachmentType;
use App\Models\System\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * 附件模型工厂
 *
 * @extends Factory<Attachment>
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['jpg', 'png', 'pdf', 'mp4', 'zip']);
        $fileName = fake()->uuid().'.'.$extension;
        $mimeType = match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            default => 'application/zip',
        };

        return [
            'uploader_id' => null,
            'uploader_type' => null,
            'disk' => 'public',
            'path' => Attachment::PATH_PREFIX.date('Y/m/d').'/'.$fileName,
            'name' => $fileName,
            'original_name' => fake()->word().'.'.$extension,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'type' => AttachmentType::fromMimeType($mimeType),
            'size' => fake()->numberBetween(1024, 10485760),
            'hash' => fake()->md5(),
        ];
    }

    /**
     * 指定存储磁盘
     */
    public function disk(string $disk): static
    {
        return $this->state(fn (array $attributes) => ['disk' => $disk]);
    }

    /**
     * 指定上传者
     */
    public function uploader(Model $uploader): static
    {
        return $this->state(fn (array $attributes) => [
            'uploader_id' => $uploader->getKey(),
            'uploader_type' => $uploader->getMorphClass(),
        ]);
    }

    /**
     * 图片附件
     */
    public function image(): static
    {
        return $this->state(function (array $attributes) {
            $fileName = fake()->uuid().'.jpg';

            return [
                'path' => Attachment::PATH_PREFIX.date('Y/m/d').'/'.$fileName,
                'name' => $fileName,
                'original_name' => fake()->word().'.jpg',
                'extension' => 'jpg',
                'mime_type' => 'image/jpeg',
                'type' => AttachmentType::IMAGE,
            ];
        });
    }
}
