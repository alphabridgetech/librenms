<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Jenkins test runner page: list jobs, run a job (with its parameters),
 * and show pass/fail per test case + console output.
 * Jenkins does the actual work; this only talks to its REST API.
 */
class JenkinsTestController extends Controller
{
    public function index()
    {
        return view('jenkins.index', [
            'configured' => $this->configured(),
            'jenkinsUrl' => config('services.jenkins.url'),
        ]);
    }

    #------------------------------------------------------------
    #                       JOB LIST
    #------------------------------------------------------------
    public function jobs()
    {
        return $this->call(function () {
            $data = $this->jenkins()
                ->get('/api/json', ['tree' => 'jobs[name,color,lastBuild[number,result,building,timestamp]]'])
                ->throw()->json();

            return collect($data['jobs'] ?? [])->map(fn ($job) => [
                'name' => $job['name'],
                'status' => $this->status($job['lastBuild'] ?? null),
                'last_build' => $job['lastBuild']['number'] ?? null,
                'last_time' => isset($job['lastBuild']['timestamp']) ? (int) ($job['lastBuild']['timestamp'] / 1000) : null,
            ])->values();
        });
    }

    #------------------------------------------------------------
    #               JOB DETAIL (parameters + builds)
    #------------------------------------------------------------
    public function job(string $job)
    {
        return $this->call(function () use ($job) {
            $data = $this->jenkins()
                ->get($this->jobPath($job) . '/api/json', ['tree' => implode(',', [
                    'name', 'inQueue',
                    'property[parameterDefinitions[name,type,description,choices,defaultParameterValue[value]]]',
                    'builds[number,result,building,timestamp,duration,estimatedDuration,actions[failCount,skipCount,totalCount]]{0,20}',
                ])])
                ->throw()->json();

            $params = collect($data['property'] ?? [])
                ->flatMap(fn ($p) => $p['parameterDefinitions'] ?? [])
                ->map(fn ($p) => [
                    'name' => $p['name'],
                    'type' => match (true) {
                        str_contains($p['type'], 'Boolean') => 'boolean',
                        str_contains($p['type'], 'Choice') => 'choice',
                        str_contains($p['type'], 'Password') => 'password',
                        default => 'string',
                    },
                    'description' => $p['description'] ?? '',
                    'choices' => $p['choices'] ?? [],
                    'default' => $p['defaultParameterValue']['value'] ?? null,
                ])->values();

            $builds = collect($data['builds'] ?? [])->map(function ($b) {
                $tests = collect($b['actions'] ?? [])->first(fn ($a) => isset($a['totalCount']));

                return [
                    'number' => $b['number'],
                    'status' => $this->status($b),
                    'time' => (int) ($b['timestamp'] / 1000),
                    'duration' => (int) (($b['building'] ? $b['estimatedDuration'] : $b['duration']) / 1000),
                    'tests' => $tests ? [
                        'total' => $tests['totalCount'],
                        'failed' => $tests['failCount'],
                        'skipped' => $tests['skipCount'],
                        'passed' => $tests['totalCount'] - $tests['failCount'] - $tests['skipCount'],
                    ] : null,
                ];
            })->values();

            return [
                'name' => $data['name'],
                'in_queue' => $data['inQueue'] ?? false,
                'params' => $params,
                'builds' => $builds,
            ];
        });
    }

    #------------------------------------------------------------
    #                       RUN A JOB
    #------------------------------------------------------------
    public function run(Request $request, string $job)
    {
        $params = $request->validate(['params' => 'array'])['params'] ?? [];

        return $this->call(function () use ($job, $params) {
            // Jobs with parameters only accept buildWithParameters; jobs without only accept build.
            $endpoint = $params ? '/buildWithParameters' : '/build';
            $this->jenkins()->asForm()->post($this->jobPath($job) . $endpoint, $params)->throw();

            return ['message' => "Job '{$job}' queued"];
        });
    }

    public function stop(string $job, int $build)
    {
        return $this->call(function () use ($job, $build) {
            $this->jenkins()->post($this->jobPath($job) . "/{$build}/stop")->throw();

            return ['message' => "Build #{$build} stopped"];
        });
    }

    #------------------------------------------------------------
    #                   TEST RESULTS (JUnit)
    #------------------------------------------------------------
    public function tests(string $job, int $build)
    {
        return $this->call(function () use ($job, $build) {
            $response = $this->jenkins()->get($this->jobPath($job) . "/{$build}/testReport/api/json", [
                'tree' => 'passCount,failCount,skipCount,suites[cases[className,name,status,duration,errorDetails]]',
            ]);

            if ($response->status() === 404) {
                return ['available' => false];
            }

            $data = $response->throw()->json();
            $cases = collect($data['suites'] ?? [])
                ->flatMap(fn ($s) => $s['cases'] ?? [])
                ->map(fn ($c) => [
                    'name' => $c['name'],
                    'group' => $c['className'],
                    // Jenkins status: PASSED/FIXED = pass, FAILED/REGRESSION = fail, SKIPPED
                    'result' => match ($c['status']) {
                        'FAILED', 'REGRESSION' => 'failed',
                        'SKIPPED' => 'skipped',
                        default => 'passed',
                    },
                    'duration' => round($c['duration'], 2),
                    'error' => $c['errorDetails'],
                ])
                ->sortBy(fn ($c) => ['failed' => 0, 'skipped' => 1, 'passed' => 2][$c['result']])
                ->values();

            return [
                'available' => true,
                'passed' => $data['passCount'] ?? 0,
                'failed' => $data['failCount'] ?? 0,
                'skipped' => $data['skipCount'] ?? 0,
                'cases' => $cases,
            ];
        });
    }

    #------------------------------------------------------------
    #               CONSOLE (incremental, for live view)
    #------------------------------------------------------------
    public function console(Request $request, string $job, int $build)
    {
        return $this->call(function () use ($request, $job, $build) {
            $response = $this->jenkins()
                ->get($this->jobPath($job) . "/{$build}/logText/progressiveText", ['start' => (int) $request->query('start', 0)])
                ->throw();

            return [
                'text' => $response->body(),
                'next' => (int) $response->header('X-Text-Size'),
                'more' => $response->header('X-More-Data') === 'true',
            ];
        });
    }

    #------------------------------------------------------------
    #                       HELPERS
    #------------------------------------------------------------
    private function configured(): bool
    {
        return config('services.jenkins.url') && config('services.jenkins.user') && config('services.jenkins.token');
    }

    private function jenkins(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.jenkins.url'), '/'))
            ->withBasicAuth(config('services.jenkins.user'), config('services.jenkins.token'))
            ->acceptJson()
            ->timeout(15);
    }

    /** "folder/job" -> "/job/folder/job/job"; rejects anything that isn't a plain job name. */
    private function jobPath(string $job): string
    {
        $parts = explode('/', $job);
        foreach ($parts as $part) {
            abort_unless(preg_match('/^[A-Za-z0-9._ -]+$/', $part), 422, 'Invalid job name');
        }

        return '/job/' . implode('/job/', array_map('rawurlencode', $parts));
    }

    private function status(?array $build): string
    {
        if (! $build) {
            return 'never';
        }
        if (! empty($build['building'])) {
            return 'running';
        }

        return strtolower($build['result'] ?? 'unknown'); // success, failure, unstable, aborted
    }

    /** Runs a Jenkins call and turns every failure into a readable JSON error. */
    private function call(callable $fn)
    {
        if (! $this->configured()) {
            return response()->json(['error' => 'Jenkins is not configured: set JENKINS_URL, JENKINS_USER and JENKINS_TOKEN in .env'], 503);
        }

        try {
            return response()->json($fn());
        } catch (ConnectionException $e) {
            return response()->json(['error' => 'Cannot reach Jenkins at ' . config('services.jenkins.url')], 502);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $code = $e->response->status();
            $msg = match ($code) {
                401 => 'Jenkins rejected the login - check JENKINS_USER / JENKINS_TOKEN',
                403 => 'Jenkins user has no permission for this action',
                404 => 'Job or build not found in Jenkins',
                default => "Jenkins returned HTTP {$code}",
            };

            return response()->json(['error' => $msg], $code >= 500 ? 502 : $code);
        }
    }
}
