<?php

namespace App\Contracts;

use App\Data\Mail\DriverHealthResult;
use App\Data\Mail\MailMessage;
use App\Data\Mail\MailSendResult;

interface MailDriver
{
    public function send(MailMessage $message): MailSendResult;

    public function health(): DriverHealthResult;
}
