<?php

namespace App\Contracts;

use App\Data\Mail\MailContent;

interface MailChannelNotification
{
    public function toMailChannel(object $notifiable): MailContent;
}
