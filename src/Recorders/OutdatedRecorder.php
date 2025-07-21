<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Pulse\Outdated\Recorders;

use DateInterval;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns\Throttling;
use RuntimeException;

class OutdatedRecorder
{
    use Throttling;

    /**
     * The events to listen for.
     *
     * @var class-string
     */
    public string $listen = SharedBeat::class;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse      $pulse,
        protected Repository $config
    )
    {
        //
    }

    public function record(SharedBeat $event): void
    {
        if ($event->time->diffInSeconds($event->time->copy()->startOfDay()) > 10) {
            Log::warning('Not Allowed to run now:' .now()->isoFormat('YYYY-MM-DD HH:mm:ss'));
            return;
        }

        // Throttle key on calendarday
        $throttleKey = 'shared-beat:composer-outdated:' . $event->time->toDateString();

        // Prevent executiion on same day
        if (!Cache::has($throttleKey)) {
            // Expire end of the day
            Cache::put($throttleKey, true, $event->time->copy()->endOfDay());

            $result = Process::run('composer outdated -D -f json');

            if ($result->failed()) {
                throw new RuntimeException('Composer outdated failed: ' . $result->errorOutput());
            }

            json_decode($result->output(), flags: JSON_THROW_ON_ERROR);

            $this->pulse->set('composer_outdated', 'result', $result->output());
            Log::warning('Outdatedrecorder has just run: ' . now()->isoFormat('YYYY-MM-DD HH:mm:ss'));
        } else {
            Log::warning('Outdated recorder cache hit.');
        }
    }
}