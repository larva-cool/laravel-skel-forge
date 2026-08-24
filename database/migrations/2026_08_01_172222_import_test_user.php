<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Models\User;
use App\Support\UserHelper;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 测试用户昵称
     */
    private array $nicknames = [
        '�', '🐱', '🐭', '🐹', '🐰', '�', '🐼', '�', '🐯', '🦁',
        '�', '🐷', '🐸', '�', '🐵', '�', '🦆', '🐥', '�', '🐌', '🐞',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->nicknames as $i => $emoji) {
            UserHelper::createByPhone('测试号'.$emoji, '14000000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'password');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $phones = array_map(fn (int $i): string => '14000000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), array_keys($this->nicknames));

        $users = User::query()->with(['extra', 'profile'])->whereIn('phone', $phones)->get();
        $users->each(function (User $user) {
            $user->delete();
        });
    }
};
