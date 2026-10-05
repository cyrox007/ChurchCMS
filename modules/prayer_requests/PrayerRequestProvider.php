<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\PrayerRequests;

interface PrayerRequestProvider
{
    public function id(): string;

    public function label(): string;

    /** @return list<array{code:string,label:string}> */
    public function services(): array;

    public function submit(PrayerRequestSubmission $submission): PrayerRequestReceipt;
}
