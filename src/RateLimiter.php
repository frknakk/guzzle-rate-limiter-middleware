<?php

namespace Spatie\GuzzleRateLimiterMiddleware;

use Psr\Http\Message\RequestInterface;

class RateLimiter
{
    const TIME_FRAME_MINUTE = 'minute';
    const TIME_FRAME_SECOND = 'second';

    /** @var int */
    protected $limit;

    /** @var string */
    protected $timeFrame;

    /** @var \Spatie\RateLimiter\Store|callable */
    protected $store;

    /** @var \Spatie\GuzzleRateLimiterMiddleware\Deferrer */
    protected $deferrer;

    /**
     * @param Store|callable $store
     */
    public function __construct(
        int $limit,
        string $timeFrame,
        $store,
        Deferrer $deferrer
    ) {
        $this->limit = $limit;
        $this->timeFrame = $timeFrame;
        $this->store = $store;
        $this->deferrer = $deferrer;
    }

    public function handle(RequestInterface $request, array $options, callable $callback)
    {
        $delayUntilNextRequest = $this->delayUntilNextRequest($request, $options);

        if ($delayUntilNextRequest > 0) {
            $this->deferrer->sleep($delayUntilNextRequest);
        }

        $this->getStore($request, $options)->push(
            $this->deferrer->getCurrentTime(),
            $this->limit
        );

        return $callback();
    }

    protected function delayUntilNextRequest(RequestInterface $request, array $options): int
    {
        $currentTimeFrameStart = $this->deferrer->getCurrentTime() - $this->timeFrameLengthInMilliseconds();

        $requestsInCurrentTimeFrame = array_values(array_filter(
            $this->getStore($request, $options)->get(),
            function (int $timestamp) use ($currentTimeFrameStart) {
                return $timestamp >= $currentTimeFrameStart;
            }
        ));

        if (count($requestsInCurrentTimeFrame) < $this->limit) {
            return 0;
        }

        $oldestRequestStartTimeRelativeToCurrentTimeFrame =
            $this->deferrer->getCurrentTime() - $requestsInCurrentTimeFrame[0];

        return $this->timeFrameLengthInMilliseconds() - $oldestRequestStartTimeRelativeToCurrentTimeFrame;
    }

    protected function timeFrameLengthInMilliseconds(): int
    {
        if ($this->timeFrame === self::TIME_FRAME_MINUTE) {
            return 60 * 1000;
        }

        return 1000;
    }

    private function getStore(RequestInterface $request, array $options)
    {
        if (is_callable($this->store)) {
            return ($this->store)($request, $options);
        } else {
            return $this->store;
        }
    }
}
