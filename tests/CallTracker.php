<?php

declare(strict_types=1);

namespace Equit\XRayTests;

/**
 * Class to facilitate tracking of method calls in test doubles.
 *
 * The general idea is that you instantiate a `CallTracker` in your TestCase then inject it into your test doubles,
 * whose methods you wish to track should call the `increment()` method. Your `TestCase` can then examine the number of
 * times the methods on the test double were called.
 */
class CallTracker
{
    /** @var int The total number of calls. */
    private int $m_overallCallCount = 0;

    /** @var int[] The counts for calls to named functions. */
    private array $m_functionCallCounts = [];

    /**
     * Fetch the current call count (for a named function).
     *
     * @param string|null $name The optional name of the function whose call count is sought. Default is `null`, in
     * which case the total call count is returned.
     *
     * @return int The number of calls.
     */
    public function callCount(?string $name = null): int
    {
        if (!isset($name)) {
            return $this->m_overallCallCount;
        } else {
            return $this->m_functionCallCounts[$name] ?? 0;
        }
    }

    /**
     * Increment the call count.
     *
     * The overall call count is incremented by 1, as is the call count for the function that called `increment()`. The
     * function is identified by examining the frame one from the top of the call stack.
     */
    public function increment(): void
    {
        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];

        if (empty($frame["type"])) {
            $name = $frame["function"];
        } else {
            $name = "{$frame["class"]}::{$frame["function"]}";
        }

        ++$this->m_overallCallCount;

        if (!isset($this->m_functionCallCounts[$name])) {
            $this->m_functionCallCounts[$name] = 1;
        } else {
            ++$this->m_functionCallCounts[$name];
        }
    }

    /**
     * Reset the call count (for a named function).
     *
     * Note that resetting a named function's call count will reduce the overall call count by the number of calls
     * recorded for that function.
     *
     * @param string|null $name The optional name of the function whose count is to be reset. If not given, the default
     * is `null` which resets all call counts, including the overall call count.
     */
    public function reset(?string $name = null): void
    {
        if (!isset($name)) {
            $this->m_overallCallCount = 0;
            $this->m_functionCallCounts = [];
        } else {
            if (isset($this->m_functionCallCounts[$name])) {
                $this->m_overallCallCount -= $this->m_functionCallCounts[$name];
            }

            unset($this->m_functionCallCounts[$name]);
        }
    }
}
