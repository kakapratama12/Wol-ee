<?php

namespace App\Http\Controllers;

use App\Models\BotInput;
use App\Models\Ingredient;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Sale;
use App\Models\Expense;
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

        $inputs = $query->limit(100)->get()->map(function (BotInput $input) {
            $entity = $input->entity_id ? $input->entity() : null;

            return [
                'id' => $input->id,
                'entity_type' => $input->entity_type,
                'entity_id' => $input->entity_id,
                'summary' => $input->summary(),
                'raw_input' => $input->raw_input,
                'parsed_data' => $input->parsed_data,
                'status' => $input->status,
                'created_at' => $input->created_at->format('d M Y H:i'),
                'edit_url' => $this->getEditUrl($input->entity_type, $input->entity_id),
                'completeness' => $this->getCompleteness($input->entity_type, $entity),
            ];
        });

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
     */
    public function archive(BotInput $botInput): \Illuminate\Http\RedirectResponse
    {
        $botInput->update(['status' => BotInput::STATUS_ARCHIVED]);

        return back()->with('success', 'Input diarsipkan.');
    }

    /**
     * Get edit URL for the entity.
     * For entities with inline editing (modal), use query param to auto-open.
     */
    private function getEditUrl(?string $entityType, ?int $entityId): ?string
    {
        if (!$entityId) {
            return null;
        }

        return match ($entityType) {
            'product' => "/products?edit={$entityId}",
            'ingredient' => "/inventory?edit={$entityId}",
            'partner' => "/partners?edit={$entityId}",
            'invoice' => "/invoices/{$entityId}/edit",
            'transaction' => "/transactions?edit={$entityId}",
            'sale' => "/sales?edit={$entityId}",
            'expense' => "/expenses?edit={$entityId}",
            default => null,
        };
    }

    /**
     * Check completeness of entity based on schema requirements.
     *
     * Returns: 'complete' | 'incomplete' | 'deleted'
     */
    private function getCompleteness(?string $entityType, $entity): string
    {
        if (!$entity) {
            return 'deleted';
        }

        return match ($entityType) {
            'product' => $this->checkProduct($entity),
            'ingredient' => $this->checkIngredient($entity),
            'recipe' => 'complete', // Recipe is always complete if it exists
            'partner' => $this->checkPartner($entity),
            'invoice' => $this->checkInvoice($entity),
            'transaction' => 'complete', // Transaction is always complete
            'sale' => 'complete', // Sale is always complete
            'expense' => 'complete', // Expense is always complete
            default => 'complete',
        };
    }

    private function checkProduct(Product $product): string
    {
        if (!$product->name || !$product->selling_price || !$product->unit) {
            return 'incomplete';
        }

        // Check if has recipe (for batch products)
        if ($product->recipe_type === 'batch' && $product->recipeItems()->count() === 0) {
            return 'incomplete';
        }

        return 'complete';
    }

    private function checkIngredient(Ingredient $ingredient): string
    {
        if (!$ingredient->name || !$ingredient->base_unit || !$ingredient->unit_price) {
            return 'incomplete';
        }

        return 'complete';
    }

    private function checkPartner(Partner $partner): string
    {
        if (!$partner->name || !$partner->type) {
            return 'incomplete';
        }

        return 'complete';
    }

    private function checkInvoice(Invoice $invoice): string
    {
        if (!$invoice->partner_id || !$invoice->amount) {
            return 'incomplete';
        }

        return 'complete';
    }
}
