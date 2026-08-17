<?php

namespace App\Notifications;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\SmtpConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
        private readonly string $module,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'  => $this->title,
            'body'   => $this->body,
            'url'    => $this->url,
            'module' => $this->module,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($notifiable instanceof User) {
            SmtpConfigService::applyFromSettings(AppSetting::forTenant((int) $notifiable->tenant_id));
        }

        return (new MailMessage)
            ->subject($this->title)
            ->line($this->body)
            ->action('Buka di SIFOBI', $this->url);
    }
}
