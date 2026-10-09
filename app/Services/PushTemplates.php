<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Push templates stored as json in resources/templates, and the command generation that the
 * Template Push page (resources/views/addhostip/template.blade.php, generateCommands()) does in
 * the browser, so the API produces exactly the same commands as the page.
 */
class PushTemplates
{
    /**
     * All templates, each with the folder it lives in
     */
    public static function all(): array
    {
        $templatesDir = resource_path('templates');
        if (! is_dir($templatesDir)) {
            return [];
        }

        $templates = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templatesDir));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'json') {
                continue;
            }

            $data = json_decode(file_get_contents($file->getPathname()), true);
            if (! is_array($data)) {
                continue;
            }

            $relativePath = str_replace($templatesDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
            $data['template_folder'] = count($parts) > 1 ? $parts[0] : '';
            $templates[] = $data;
        }

        usort($templates, fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return $templates;
    }

    /**
     * Find a template by name (or slug), optionally inside a folder
     */
    public static function find(string $name, ?string $folder = null): ?array
    {
        $slug = Str::slug($name);
        foreach (self::all() as $template) {
            if ($folder !== null && $folder !== '' && Str::slug($template['template_folder']) !== Str::slug($folder)) {
                continue;
            }
            if (Str::slug($template['name'] ?? '') === $slug) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Same check as templateMatchesHardware() on the page
     */
    public static function matchesHardware(array $template, ?string $hardware): bool
    {
        $models = array_filter(array_map([self::class, 'normalizeModel'], $template['hardware_models'] ?? []));
        if (empty($models) || empty($hardware)) {
            return true;
        }

        return in_array(self::normalizeModel($hardware), $models, true);
    }

    /**
     * Describe the input each field of a form template expects in "values"
     */
    public static function describeFields(array $template): array
    {
        $fields = [];
        foreach ($template['fields'] ?? [] as $field) {
            $type = $field['type'] ?? 'text';
            if ($type === 'putonlycmd') {
                continue;
            }

            $info = [
                'label' => $field['label'],
                'type' => $type,
                'required' => ($field['required'] ?? true) !== false && $type !== 'checkbox' && $type !== 'dynamic_list',
                'command' => $field['command'] ?? '',
            ];

            if ($type === 'dropdown') {
                $info['options'] = array_map(fn ($o) => self::splitOption($o)[0], self::options($field));
            } elseif ($type === 'checkbox') {
                $info['value'] = 'true or false';
            } elseif ($type === 'dynamic_list') {
                $info['value'] = 'list of rows, each row an object with: ' . implode(', ', self::templateVariables($field['command'] ?? ''));
            }
            if (! empty($field['divisor'])) {
                $info['divisor'] = $field['divisor'];
            }

            $fields[] = $info;
        }

        return $fields;
    }

    /**
     * Build the cli commands for a template.
     *
     * @param  array  $values  form templates: field label => value (dropdown: option name, checkbox: bool, dynamic_list: rows)
     * @param  string[]  $interfaces  interfaces the template is applied to
     * @param  array  $options  non-form templates: port_mode (access|trunk|custom), pvid, commands
     *
     * @throws InvalidArgumentException when a value is missing or invalid
     */
    public static function buildCommands(array $template, array $values, array $interfaces, array $options = []): array
    {
        if (($template['type'] ?? '') === 'form') {
            $coreCommands = self::formCommands($template['fields'] ?? [], $values);
        } else {
            $coreCommands = self::portModeCommands($template, $options);
        }

        return self::applyInterfaces($coreCommands, $interfaces);
    }

    private static function formCommands(array $fields, array $values): array
    {
        $errors = [];

        // accept field labels in any letter case
        $byLowerLabel = array_change_key_case($values, CASE_LOWER);
        foreach ($fields as $field) {
            $label = $field['label'] ?? '';
            if ($label !== '' && ! array_key_exists($label, $values) && array_key_exists(strtolower($label), $byLowerLabel)) {
                $values[$label] = $byLowerLabel[strtolower($label)];
            }
        }

        // values other fields can reference with {{field:Label}}
        $fieldValues = [];
        foreach ($fields as $field) {
            $label = $field['label'] ?? '';
            $type = $field['type'] ?? 'text';
            if ($label === '' || $type === 'putonlycmd' || $type === 'checkbox') {
                continue;
            }
            $raw = $values[$label] ?? null;
            if (is_scalar($raw) && trim((string) $raw) !== '') {
                $fieldValues[$label] = trim((string) $raw);
            } else {
                $fieldValues[$label] = $label;
            }
        }

        $commands = [];
        foreach ($fields as $field) {
            $label = $field['label'] ?? '';
            $type = $field['type'] ?? 'text';
            $template = $field['command'] ?? '';

            if ($type === 'dynamic_list') {
                $rows = $values[$label] ?? [];
                if (! is_array($rows)) {
                    $errors[] = "\"$label\" must be a list of rows";
                    continue;
                }
                foreach ($rows as $row) {
                    $cmd = $template;
                    foreach (self::templateVariables($template) as $var) {
                        $val = trim((string) ($row[$var] ?? ''));
                        $varLabel = ucwords(str_replace('_', ' ', $var));
                        $cmd = str_replace('{{' . $var . '}}', $val !== '' ? $val : $varLabel, $cmd);
                    }
                    $commands[] = $cmd;
                }
                continue;
            }

            $template = preg_replace_callback('/\{\{field:([^}]+)\}\}/', function ($m) use ($fieldValues) {
                return $fieldValues[trim($m[1])] ?? $m[0];
            }, $template);

            if ($type === 'checkbox') {
                if (filter_var($values[$label] ?? false, FILTER_VALIDATE_BOOLEAN) && $template !== '') {
                    $commands[] = $template;
                }
                continue;
            }

            if ($type === 'putonlycmd') {
                if ($template !== '') {
                    $commands[] = $template;
                }
                continue;
            }

            $raw = $values[$label] ?? null;
            $isFilled = is_scalar($raw) && trim((string) $raw) !== '';
            if (! $isFilled) {
                // the page would push the label as a placeholder, refuse instead
                if (($field['required'] ?? true) !== false) {
                    $errors[] = "\"$label\" is required";
                }
                continue;
            }
            $raw = trim((string) $raw);

            if ($type === 'dropdown') {
                $option = collect(self::options($field))->first(fn ($o) => strcasecmp(self::splitOption($o)[0], $raw) === 0);
                if ($option === null) {
                    $names = implode(', ', array_map(fn ($o) => self::splitOption($o)[0], self::options($field)));
                    $errors[] = "\"$label\" must be one of: $names";
                    continue;
                }
                $parts = self::splitOption($option);
                $cmd = count($parts) === 2 ? $parts[1] : str_replace('{{value}}', $parts[0], $template);
            } else {
                $val = $raw;
                $divisor = (float) ($field['divisor'] ?? 0);
                if ($divisor > 0) {
                    $num = is_numeric($val) ? (float) $val : $divisor;
                    $val = (string) round(max($num, $divisor) / $divisor);
                }
                $cmd = str_replace('{{value}}', $val, $template);
            }

            if ($cmd !== '') {
                $commands[] = $cmd;
            }
        }

        if (! empty($errors)) {
            throw new InvalidArgumentException(implode('; ', $errors));
        }

        return $commands;
    }

    private static function portModeCommands(array $template, array $options): array
    {
        $mode = $options['port_mode'] ?? ($template['type'] ?? 'custom');
        $pvid = (string) ($options['pvid'] ?? $template['pvid'] ?? '1');

        return match ($mode) {
            'access' => ['switchport mode access', 'switchport pvid ' . $pvid],
            'trunk' => ['switchport mode trunk', 'switchport pvid ' . $pvid],
            default => array_values(array_filter(array_map('trim', $options['commands'] ?? $template['commands'] ?? []), fn ($c) => $c !== '')),
        };
    }

    /**
     * Repeat the interface blocks for every selected interface, like the page does
     */
    private static function applyInterfaces(array $coreCommands, array $interfaces): array
    {
        $interfaces = array_values(array_filter(array_map('trim', $interfaces)));
        if (empty($interfaces)) {
            return array_map(fn ($cmd) => str_replace('{{interface}}', '', $cmd), $coreCommands);
        }

        $hasInterfaceVar = collect($coreCommands)->contains(fn ($cmd) => str_contains($cmd, '{{interface}}'));
        if (! $hasInterfaceVar) {
            $blocks = [['type' => 'interface', 'commands' => array_merge(['interface {{interface}}'], $coreCommands)]];
        } else {
            $blocks = [];
            $current = ['type' => 'global', 'commands' => []];
            foreach ($coreCommands as $cmd) {
                if (str_contains($cmd, '{{interface}}')) {
                    if (! empty($current['commands'])) {
                        $blocks[] = $current;
                    }
                    $current = ['type' => 'interface', 'commands' => [$cmd]];
                } elseif (trim($cmd) === '!') {
                    if (! empty($current['commands'])) {
                        $blocks[] = $current;
                    }
                    $current = ['type' => 'global', 'commands' => [$cmd]];
                } else {
                    $current['commands'][] = $cmd;
                }
            }
            if (! empty($current['commands'])) {
                $blocks[] = $current;
            }
        }

        $final = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'global') {
                array_push($final, ...$block['commands']);
                continue;
            }
            foreach ($interfaces as $interface) {
                foreach ($block['commands'] as $cmd) {
                    $final[] = str_replace('{{interface}}', $interface, $cmd);
                }
            }
        }

        return $final;
    }

    private static function templateVariables(string $command): array
    {
        preg_match_all('/\{\{([^}]+)\}\}/', $command, $matches);

        return array_values(array_unique(array_filter(array_map('trim', $matches[1]), function ($var) {
            return $var !== 'interface' && ! str_starts_with($var, 'field:');
        })));
    }

    private static function options(array $field): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) ($field['options'] ?? '')))));
    }

    /**
     * "name:command" like javascript's split(':', 2)
     */
    private static function splitOption(string $option): array
    {
        return array_map('trim', array_slice(explode(':', $option), 0, 2));
    }

    private static function normalizeModel(?string $model): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $model));
    }
}
