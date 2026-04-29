<?php

namespace App\Services\Tracking\Track17;

class Track17Service
{

    public function register(string $number, string $code): bool
    {
        return (new Track17Api())->register($number, $code);
    }

    public function query(string $number, string $code, bool $isReturnTrackList = false)
    {
        $service = new Track17Api();

        $trackInfo = $service->get($number, $code, $isReturnTrackList);

        if ($trackInfo) {
            return $trackInfo;
        }
    }


}
