<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Services\ConsumableGroupInsightsService;
use App\Domain\Products\Services\ConsumableGroupService;
use App\Http\Requests\ConsumableGroupRequest;
use App\Http\Requests\MergeConsumableGroupRequest;
use App\Http\Requests\MoveProductRequest;
use App\Http\Requests\ReceiptItemConsumptionRequest;
use App\Models\ConsumableGroup;
use App\Models\Product;
use App\Models\ReceiptItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One general name on the Insights page ("Milk"), and the corrections the
 * user can make to how their purchases are grouped.
 */
class ConsumableGroupController extends Controller
{
    public function __construct(
        private ConsumableGroupInsightsService $insightsService,
        private ConsumableGroupService $groupService,
    ) {}

    public function show(ConsumableGroup $consumableGroup): Response
    {
        $this->authorize('view', $consumableGroup);

        return Inertia::render('Insights/Group', $this->insightsService->detail($consumableGroup));
    }

    public function update(ConsumableGroupRequest $request, ConsumableGroup $consumableGroup): RedirectResponse
    {
        $this->authorize('update', $consumableGroup);

        $group = $this->groupService->rename($consumableGroup, $request->validated('name'), $request->validated('unit'));

        return redirect()->route('insights.groups.show', $group)
            ->with('success', $group->is($consumableGroup) ? 'Name saved.' : "Merged into {$group->name}.");
    }

    public function merge(MergeConsumableGroupRequest $request, ConsumableGroup $consumableGroup): RedirectResponse
    {
        $this->authorize('update', $consumableGroup);

        $target = ConsumableGroup::query()->findOrFail($request->validated('into'));
        $this->groupService->merge($consumableGroup, $target);

        return redirect()->route('insights.groups.show', $target)->with('success', "Merged into {$target->name}.");
    }

    public function moveProduct(MoveProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $group = $this->groupService->moveProduct($product, $request->validated('group_name'));

        return back()->with('success', "{$product->name} now counts as {$group->name}.");
    }

    public function setLineCounts(ReceiptItemConsumptionRequest $request, ReceiptItem $receiptItem): RedirectResponse
    {
        $this->authorize('update', $receiptItem->receipt);

        $this->groupService->setCounts($receiptItem, $request->boolean('counts'));

        return back();
    }
}
