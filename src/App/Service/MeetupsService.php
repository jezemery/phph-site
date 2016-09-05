<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Meetup;
use DateTimeImmutable;

final class MeetupsService implements MeetupsServiceInterface
{
    /**
     * @var string
     */
    private $meetupsDataPath;

    /**
     * @var Meetup[]
     */
    private $cachedDirectoryListing;

    public function __construct(string $meetupsDataPath)
    {
        $this->meetupsDataPath = $meetupsDataPath;
    }

    /**
     * Get and cache the list of meetups from the data directory, ordered by
     * most recent first.
     *
     * @return string[]
     */
    private function getMeetupsList() : array
    {
        if (!is_array($this->cachedDirectoryListing)) {
            $this->cachedDirectoryListing = array();

            $directoryIterator = new \RecursiveDirectoryIterator($this->meetupsDataPath);
            $iteratorIterator = new \RecursiveIteratorIterator($directoryIterator);

            foreach ($iteratorIterator as $file) {
                /* @var $file \DirectoryIterator */
                if (substr($file->getFilename(), -4) === '.php') {
                    $this->cachedDirectoryListing[] = str_replace($this->meetupsDataPath, '', $file->getPathname());
                }
            }

            rsort($this->cachedDirectoryListing);
        }

        return $this->cachedDirectoryListing;
    }

    /**
     * Get all the future meetups as an array
     * @return Meetup[]
     * @throws \App\Service\Exception\MeetupDataNotFound
     * @throws \App\Service\Exception\InvalidMeetupData
     */
    public function getFutureMeetups() : array
    {
        $meetups = $this->getMeetupsList();

        $now = new DateTimeImmutable();

        $future_meetups = array();

        foreach ($meetups as $meetup) {
            $date = $this->extractDateTimeFromMeetupFilename($meetup);
            $diff = $date->diff($now);
            if ($diff->invert || $diff->days === 0) {
                $future_meetups[$date->format('Ymd')] = $this->getMeetup($meetup);
            }
        }

        asort($future_meetups);

        if (count($future_meetups) > 0) {
            return $future_meetups;
        } else {
            return array();
        }
    }

    /**
     * Get all the past meetups as an array
     * @return Meetup[]
     * @throws \App\Service\Exception\MeetupDataNotFound
     * @throws \App\Service\Exception\InvalidMeetupData
     */
    public function getPastMeetups() : array
    {
        $meetups = $this->getMeetupsList();

        $now = new DateTimeImmutable();

        $pastMeetups = [];

        foreach ($meetups as $meetup) {
            $date = $this->extractDateTimeFromMeetupFilename($meetup);
            $diff = $date->diff($now);
            if (!$diff->invert) {
                $pastMeetups[$date->format('Ymd')] = $this->getMeetup($meetup);
            }
        }

        arsort($pastMeetups);

        return $pastMeetups;
    }

    /**
     * Do a dumb extraction of the date from a meetup filename
     *
     * @param string $meetup
     * @return DateTimeImmutable
     */
    private function extractDateTimeFromMeetupFilename(string $meetup) : DateTimeImmutable
    {
        return new DateTimeImmutable(str_replace('.php', '', substr($meetup, strrpos($meetup, '/')+1)));
    }

    /**
     * Load an individual meetup file
     *
     * @param  string $file The file (from the cached directory list)
     * @return Meetup
     * @throws \App\Service\Exception\InvalidMeetupData
     * @throws \App\Service\Exception\MeetupDataNotFound
     */
    private function getMeetup(string $file) : Meetup
    {
        $fullPath = $this->meetupsDataPath . $file;

        if (!file_exists($fullPath)) {
            throw Exception\MeetupDataNotFound::fromFilename($file);
        }

        $meetup = include $fullPath;

        if (!($meetup instanceof Meetup)) {
            throw Exception\InvalidMeetupData::fromFilenameAndData($file, $meetup);
        }

        return $meetup;
    }
}
