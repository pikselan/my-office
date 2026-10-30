<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Menjalankan seluruh kelas uji pada tests/.
 */
final class TestRunner
{
    private string $basePath;

    /** @var list<string> */
    private array $suites = ['Unit', 'Integration', 'Security'];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * @return list<class-string>
     */
    public function discover(?string $suite = null): array
    {
        $classes = [];

        foreach ($this->suites as $name) {
            if ($suite !== null && strtolower($suite) !== strtolower($name)) {
                continue;
            }

            $pattern = $this->basePath . '/tests/' . $name . '/*Test.php';

            foreach (glob($pattern) ?: [] as $file) {
                $class = 'Tests\\' . $name . '\\' . basename($file, '.php');

                if (class_exists($class)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * @return array{passed: int, failed: int, assertions: int, failures: list<string>, classes: int}
     */
    public function run(?string $suite = null, ?callable $output = null): array
    {
        $passed = 0;
        $failed = 0;
        $assertions = 0;
        $failures = [];
        $classes = 0;

        foreach ($this->discover($suite) as $class) {
            $classes++;
            $instance = new $class();

            if ($output !== null) {
                $output('class', $class);
            }

            foreach ($instance->methods() as $method) {
                $instance = new $class();
                $instance->{$method}();

                $assertions += $instance->assertionCount();
                $methodFailures = $instance->failures();

                if ($methodFailures === []) {
                    $passed++;
                    if ($output !== null) {
                        $output('pass', $class . '::' . $method);
                    }
                    continue;
                }

                $failed++;
                foreach ($methodFailures as $failure) {
                    $failures[] = $class . '::' . $failure;
                    if ($output !== null) {
                        $output('fail', $class . '::' . $failure);
                    }
                }
            }
        }

        return [
            'passed'     => $passed,
            'failed'     => $failed,
            'assertions' => $assertions,
            'failures'   => $failures,
            'classes'    => $classes,
        ];
    }
}
