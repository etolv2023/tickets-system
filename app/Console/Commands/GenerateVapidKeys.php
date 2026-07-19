<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * F20.2 — one-time VAPID keypair for Web Push.
 *
 * Deliberately prints rather than writes .env: the private key is a secret, and
 * a command that silently rewrites a config file is a command that eventually
 * overwrites the working key on a server somebody was mid-deploy on.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'يولّد مفاتيح VAPID لإشعارات المتصفح';

    public function handle(): int
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($key === false) {
            $this->error('openssl مش قادر يولّد مفتاح EC. اتأكد إن الإكستنشن متسطّب.');

            return self::FAILURE;
        }

        $details = openssl_pkey_get_details($key);

        // The public key is the uncompressed point: 0x04 || X || Y, 65 bytes.
        $public = "\x04"
            . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        $private = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);

        $this->newLine();
        $this->line('حط السطرين دول في .env — والمفتاح الخاص سر، متحطهوش في git:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY=' . $this->base64url($public));
        $this->line('VAPID_PRIVATE_KEY=' . $this->base64url($private));
        $this->newLine();
        $this->warn('لو غيّرت المفتاح العام بعدين، كل الاشتراكات الحالية بتبطل وكل واحد لازم يفعّل الإشعارات تاني.');

        return self::SUCCESS;
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
