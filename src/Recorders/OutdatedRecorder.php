<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Pulse\Outdated\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Cache;
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
        if ($event->time->copy()->startOfDay()->diffInSeconds($event->time) > 10) {
            return;
        }

        // Throttle key on calendar day
        $throttleKey = 'shared-beat:composer-outdated:' . $event->time->toDateString();

        // Prevent execution on same day
        if (!Cache::has($throttleKey)) {
            // Expire end of the day
            Cache::put($throttleKey, true, $event->time->copy()->endOfDay());

            $result = Process::run('composer outdated -D -f json');

            if ($result->failed()) {
                throw new RuntimeException('Composer outdated failed: ' . $result->errorOutput());
            }

            json_decode($result->output(), flags: JSON_THROW_ON_ERROR);

            $this->pulse->set('composer_outdated', 'result', $result->output());
        }
    }
}
