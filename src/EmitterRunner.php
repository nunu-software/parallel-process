<?php

namespace NunuSoftware\ParallelProcess;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Symfony\Component\Process\Process;

class EmitterRunner implements ArrayAccess, IteratorAggregate
{
    /**
     * @var Process[]
     */
    protected $processes = [];

    /**
     * @var string[]
     */
    protected $pending = [];

    /**
     * @var string[]
     */
    protected $running = [];

    /**
     * @var int
     */
    protected $poolSize = 5;

    public function __construct(array $processes = [], $poolSize = null)
    {
        foreach ($processes as $key => $process) {
            $this->set($key, $process);
        }
    }

    /**
     * @param callable $onFinished
     *
     * @return void
     */
    public function run(callable $onFinished)
    {
        foreach ($this->processes as $key => $process) {
            $this->pending[] = $key;
        }

        while (count($this->pending) || count($this->running)) {
            foreach ($this->running as $index => $key) {
                if (!$this->processes[$key]->isRunning()) {
                    $onFinished($this->processes[$key], $key);
                    unset($this->running[$index]);
                    unset($this->processes[$key]);
                }
            }

            while (count($this->pending) && ($this->poolSize < 1 || count($this->running) < $this->poolSize)) {
                $key = array_shift($this->pending);
                $this->processes[$key]->start();
                $this->running[] = $key;
            }

            $output = [];
            foreach ($this->processes as $key => $process) {
                $output[$key] = $process->getStatus();
            }

            usleep(10000);
        }
    }

    /**
     * @param string  $key
     * @param Process $process
     *
     * @return $this
     */
    public function set($key, Process $process)
    {
        $this->processes[$key] = $process;

        return $this;
    }

    /**
     * @param string $key
     *
     * @return $this
     */
    public function remove($key)
    {
        unset($this->processes[$key]);

        return $this;
    }

    /**
     * @param string $key
     *
     * @return Process
     */
    public function get($key)
    {
        return $this->processes[$key];
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->processes);
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @return int
     */
    public function getPoolSize()
    {
        return $this->poolSize;
    }

    /**
     * @param int $poolSize
     *
     * @return $this
     */
    public function setPoolSize($poolSize)
    {
        $this->poolSize = $poolSize;

        return $this;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->processes);
    }
}
