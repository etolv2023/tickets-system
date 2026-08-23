<?php

namespace App\Console\Commands;

use App\Models\TicketDiscordMessage;
use App\Models\User;
use App\Services\DiscordService;
use Illuminate\Console\Command;

/**
 * Answers "is Discord actually going to work?" without opening a ticket.
 *
 * Every failure mode here looks identical from the application's side — nothing
 * arrives — so the point is to separate them: a refused token, a channel the bot
 * cannot see, and a team with no Discord ids are three different problems with
 * three different fixes.
 */
class CheckDiscord extends Command
{
    protected $signature = 'discord:check
        {--message : Post a real test message to the tickets channel}
        {--dm= : Send a test DM to this user email}';

    protected $description = 'يفحص إعدادات ديسكورد: التوكن، السيرفر، القناة، وربط المستخدمين';

    public function handle(DiscordService $discord): int
    {
        $this->components->info('إعدادات ديسكورد');

        $this->line('  DISCORD_ENABLED      : ' . (config('discord.enabled') ? 'true' : 'false'));
        $this->line('  BOT_TOKEN            : ' . (filled(config('discord.bot_token')) ? 'موجود' : '— ناقص —'));
        $this->line('  GUILD_ID             : ' . (config('discord.guild_id') ?: '— ناقص —'));
        $this->line('  TICKETS_CHANNEL_ID   : ' . (config('discord.tickets_channel_id') ?: '— ناقص —'));
        $this->line('  DRY_RUN              : ' . (config('discord.dry_run') ? 'true' : 'false'));
        $this->line('  FORUM_MODE           : ' . (config('discord.forum_mode') ? 'true' : 'false'));
        $this->line('  QUEUE                : ' . config('discord.queue'));

        if (! $discord->configured()) {
            $this->components->warn('التكامل مقفول — النظام هيشتغل عادي من غير أي نداء لديسكورد.');

            return self::SUCCESS;
        }

        if ($discord->dryRun()) {
            $this->components->warn('DRY_RUN شغال — مفيش أي نداء حقيقي لديسكورد.');

            return self::SUCCESS;
        }

        $identity = $discord->identity();

        if ($identity === null) {
            $this->components->error('التوكن مرفوض — ديسكورد مردّش على /users/@me.');

            return self::FAILURE;
        }

        $this->components->info("البوت: {$identity['username']} ({$identity['id']})");

        $channel = $discord->channel((string) config('discord.tickets_channel_id'));

        if ($channel === null) {
            $this->components->error('القناة مش ظاهرة للبوت — اتأكد إنه متضاف للسيرفر وعنده View Channel.');

            return self::FAILURE;
        }

        $this->components->info("القناة: #{$channel['name']}  (type {$channel['type']})");

        if (! $this->channelShapeOk($channel)) {
            return self::FAILURE;
        }

        if (! $this->permissionReport($discord)) {
            return self::FAILURE;
        }

        $this->mappingReport($discord);
        $this->unverifiedReport();

        if ($this->option('message')) {
            $result = $discord->postMessage(
                (string) config('discord.tickets_channel_id'),
                ['content' => 'اختبار اتصال من نظام التذاكر ✅'],
            );

            $result->ok
                ? $this->components->info('الرسالة التجريبية اتبعتت.')
                : $this->components->error('فشل إرسال الرسالة: ' . $result->error);
        }

        if ($email = $this->option('dm')) {
            $this->testDm($discord, $email);
        }

        return self::SUCCESS;
    }

    /**
     * Forum mode needs a forum channel. Anything else and posts cannot be
     * opened at all, so it is better said here than discovered by a queue full
     * of failures.
     *
     * @param  array<string, mixed>  $channel
     */
    private function channelShapeOk(array $channel): bool
    {
        $isForum = (int) ($channel['type'] ?? -1) === DiscordService::CHANNEL_FORUM;

        if (config('discord.forum_mode') && ! $isForum) {
            $this->components->error(
                'وضع الفوروم مفعّل بس القناة دي مش Forum Channel (النوع لازم يكون '
                . DiscordService::CHANNEL_FORUM . '). غيّر DISCORD_TICKETS_CHANNEL_ID لقناة فوروم، '
                . 'أو اقفل DISCORD_FORUM_MODE.'
            );

            return false;
        }

        if (! config('discord.forum_mode') && $isForum) {
            $this->components->error('القناة دي فوروم بس وضع الفوروم مقفول — شغّل DISCORD_FORUM_MODE.');

            return false;
        }

        // A forum that demands a tag on every post would refuse ours.
        if ($isForum && ((int) ($channel['flags'] ?? 0) & 16) === 16) {
            $this->components->warn('الفوروم بيطلب Tag إجباري على كل بوست — البوستات هتترفض. شيل الإلزام أو ضيف tag افتراضي.');
        }

        return true;
    }

    /**
     * What the bot may actually do here.
     *
     * Manage Channels is reported but never required: the forum is created by a
     * person, and opening posts inside it does not need it.
     */
    private function permissionReport(DiscordService $discord): bool
    {
        $perms = $discord->channelPermissions((string) config('discord.tickets_channel_id'));

        if ($perms === null) {
            $this->components->warn('مقدرناش نحسب صلاحيات البوت على القناة — راجع GUILD_ID.');

            return true;
        }

        $required = ['View Channels', 'Send Messages', 'Embed Links', 'Read Message History'];

        if (config('discord.forum_mode')) {
            $required[] = 'Send Messages in Threads';
        } else {
            $required[] = 'Create Public Threads';
            $required[] = 'Send Messages in Threads';
        }

        $this->newLine();
        $this->components->info('صلاحيات البوت على القناة');

        $missing = [];

        foreach ($required as $label) {
            $ok = $perms[$label] ?? false;
            $this->line(sprintf('  %-28s %s', $label, $ok ? '✔' : '✘ ناقصة'));

            if (! $ok) {
                $missing[] = $label;
            }
        }

        $this->line(sprintf('  %-28s %s', 'Manage Channels', ($perms['Manage Channels'] ?? false) ? 'موجودة (مش مطلوبة)' : 'مش موجودة — وده تمام'));
        $this->line(sprintf('  %-28s %s', 'Administrator', ($perms['Administrator'] ?? false) ? '⚠ موجودة — مش مطلوبة' : 'مش موجودة — وده تمام'));

        if ($missing !== []) {
            $this->components->error('صلاحيات ناقصة: ' . implode('، ', $missing));

            return false;
        }

        return true;
    }

    /** Who can actually be reached, and who cannot. */
    private function mappingReport(DiscordService $discord): void
    {
        $users = User::query()->active()->orderBy('name')->get(['id', 'name', 'email', 'discord_user_id']);

        $rows = [];

        foreach ($users as $user) {
            if (blank($user->discord_user_id)) {
                $rows[] = [$user->name, $user->email, '— مفيش —', 'مش هيوصله DM'];

                continue;
            }

            $member = $discord->guildMemberExists((string) $user->discord_user_id);

            $rows[] = [
                $user->name,
                $user->email,
                $user->discord_user_id,
                match ($member) {
                    true => 'في السيرفر ✔',
                    false => 'مش في السيرفر ✘',
                    default => 'معرفناش',
                },
            ];
        }

        $this->newLine();
        $this->components->info('ربط المستخدمين بديسكورد');
        $this->table(['الاسم', 'الإيميل', 'Discord ID', 'الحالة'], $rows);

        $missing = $users->whereNull('discord_user_id')->count();

        if ($missing > 0) {
            $this->components->warn("{$missing} مستخدم من غير Discord ID — التوزيع بيشتغل عادي، بس مش هيوصلهم DM. كل واحد بيحطه بنفسه من /profile.");
        }
    }

    /**
     * Rows we could not confirm either way.
     *
     * The only rows a human has to look at.
     *
     * unverified: the system deliberately did not resend, because it could not
     * rule out that Discord already had the message. There is no force-resend
     * command on purpose — the safe action is a person checking the channel.
     *
     * failed: Discord refused it for a reason that will not fix itself.
     */
    private function unverifiedReport(): void
    {
        $rows = TicketDiscordMessage::query()
            ->needsAttention()
            ->latest('id')
            ->limit(20)
            ->get(['id', 'ticket_id', 'user_id', 'type', 'status', 'error', 'created_at']);

        if ($rows->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->components->warn('رسايل محتاجة حد يبصّ عليها:');
        $this->line('  unverified = يمكن تكون وصلت فعلاً. النظام مبيعيدش إرسالها بالقصد عشان ماتتكررش —');
        $this->line('               راجع القناة أو الخاص بنفسك وقرر.');
        $this->line('  failed     = ديسكورد رفضها لسبب مش هيتصلح لوحده (توكن، صلاحية، أو الشخص قافل الخاص).');

        $this->table(
            ['#', 'تذكرة', 'لمين', 'النوع', 'الحالة', 'السبب', 'اتعملت'],
            $rows->map(fn ($r) => [
                $r->id,
                $r->ticket_id,
                $r->user_id ? (User::find($r->user_id)?->name ?? $r->user_id) : '— قناة —',
                $r->type,
                $r->status,
                mb_substr((string) $r->error, 0, 40),
                (string) $r->created_at,
            ])->all()
        );

        $unverified = $rows->where('status', TicketDiscordMessage::STATUS_UNVERIFIED)->count();

        if ($unverified > 0) {
            $this->components->warn("{$unverified} رسالة حالتها unverified — مفيش إعادة إرسال أوتوماتيك ليها بالقصد.");
        }
    }

    private function testDm(DiscordService $discord, string $email): void
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->components->error("مفيش مستخدم بالإيميل ده: {$email}");

            return;
        }

        if (blank($user->discord_user_id)) {
            $this->components->error("{$user->name} مالوش Discord ID.");

            return;
        }

        $dm = $discord->openDm((string) $user->discord_user_id);

        if (! $dm->ok) {
            $this->components->error('مقدرناش نفتح محادثة خاصة: ' . $dm->error);

            return;
        }

        $result = $discord->postMessage((string) $dm->messageId, ['content' => 'اختبار من نظام التذاكر ✅']);

        $result->ok
            ? $this->components->info("الرسالة الخاصة وصلت لـ {$user->name}.")
            : $this->components->error('فشل الإرسال: ' . $result->error);
    }
}
