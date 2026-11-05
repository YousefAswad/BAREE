<?php

namespace App\Notifications;

use App\Models\AssessmentCampaign;
use App\Notifications\Concerns\PreferenceAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CampaignAssignedNotification extends Notification implements ShouldQueue
{
    use PreferenceAware, Queueable;

    public function __construct(private AssessmentCampaign $campaign) {}

    protected function preferenceType(): string
    {
        return 'assignment';
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'campaign', 'campaign_id' => $this->campaign->id, 'name' => $this->campaign->name, 'ends_at' => $this->campaign->ends_at];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('تقييم جديد: :name', ['name' => $this->campaign->name]))
            ->line(__('مؤسستك دعتك لتقييم جاهزية قصير.'))
            ->action(__('ابدأ التقييم'), url('/assessments/'.$this->campaign->template->key.'/start'));
    }
}
