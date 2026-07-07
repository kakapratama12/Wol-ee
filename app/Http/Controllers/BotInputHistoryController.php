<?php

namespace App\Http\Controllers;

use App\Models\BotInput;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotInputHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = BotInput::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->latest();

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $inputs = $query->limit(100)->get()->map(fn (BotInput $input) => [
            'id' => $input->id,
            'entity_type' => $input->entity_type,
            'entity_id' => $input->entity_id,
            'summary' => $input->summary(),
            'raw_input' => $input->raw_input,
            'parsed_data' => $input->parsed_data,
            'status' => $input->status,
            'created_at' => $input->created_at->format('d M Y H:i'),
        ]);

        return Inertia::render('Settings/BotInputHistory', [
            'inputs' => $inputs->values()->all(),
            'filters' => [
                'entity_type' => $request->string('entity_type', ''),
                'status' => $request->string('status', 'active'),
            ],
        ]);
    }

    /**
     * Archive a bot input (hide from active view).
     *
     * PUT /settings/bot/history/{botInput}/archive
     */
    public function archive(BotInput $botInput): \Illuminate\Http\RedirectResponse
    {
        $botInput->update(['status' => BotInput::STATUS_ARCHIVED]);

        return back()->with('success', 'Input diarsipkan.');
    }
}
