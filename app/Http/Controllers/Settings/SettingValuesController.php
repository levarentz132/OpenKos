<?php

namespace App\Http\Controllers\Settings;

use App\Events\Settings\SettingsUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use OpenKOS\Core\Events\SettingsUpdated as PlatformSettingsUpdated;
use OpenKOS\Platform\Settings\SettingsManager;

class SettingValuesController extends Controller
{
    public function __construct(
        private SettingsManager $manager,
    ) {}

    public function edit(Request $request, string $page): Response
    {
        return Inertia::render('settings/dynamic', [
            'page' => $page,
            'definitions' => array_values($this->manager->definitions($page)),
            'values' => $this->manager->all($page),
        ]);
    }

    public function upsert(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        try {
            $this->manager->set($data['key'], $data['value'], $request->user());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'value' => $e->getMessage(),
            ]);
        }

        event(new SettingsUpdated(
            group: $this->manager->definitions()[$data['key']]->page ?? 'general',
            keys: [$data['key']],
            actorId: $request->user()?->getKey(),
        ));

        event(new PlatformSettingsUpdated(
            group: $this->manager->definitions()[$data['key']]->page ?? 'general',
            keys: [$data['key']],
            actorId: $request->user()?->getKey(),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Setting updated.')]);

        return redirect()->back();
    }
}
