<?php

namespace Koodilab\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Koodilab\Http\Controllers\Controller;
use Koodilab\Models\Building;
use Koodilab\Models\Grid;
use Koodilab\Models\Training;
use Koodilab\Models\Transformers\TrainerTransformer;
use Koodilab\Models\Unit;

class TrainerController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('player');
    }

    /**
     * Show the trainer in json format.
     *
     * @param Grid               $grid
     * @param TrainerTransformer $transformer
     *
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function index(Grid $grid, TrainerTransformer $transformer)
    {
        $this->authorizeTrainer($grid);

        return $transformer->transform($grid);
    }

    /**
     * Store a newly created training in storage.
     *
     * @param Request $request
     * @param Grid    $grid
     * @param Unit    $unit
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Grid $grid, Unit $unit)
    {
        $this->authorizeTrainer($grid);

        if ($grid->training) {
            return $this->createBadRequestJsonResponse();
        }

        $quantity = (int) $request->get('quantity', 0);

        if ($quantity <= 0) {
            return $this->createBadRequestJsonResponse();
        }

        /** @var \Koodilab\Models\User $user */
        $user = auth()->user();

        if (!$user->hasUnit($unit)) {
            return $this->createBadRequestJsonResponse();
        }

        if (!$user->hasEnergy($quantity * $unit->train_cost)) {
            return $this->createBadRequestJsonResponse();
        }

        if ($grid->planet->free_supply < $quantity * $unit->supply) {
            return $this->createBadRequestJsonResponse();
        }

        $grid->building->applyModifiers([
            'level' => $grid->level,
        ]);

        $unit->applyModifiers([
            'train_time_bonus' => $grid->building->train_time_bonus,
        ]);

        DB::transaction(function () use ($grid, $unit, $quantity) {
            Training::createFrom($grid, $unit, $quantity);
        });
    }

    /**
     * Remove the training from storage.
     *
     * @param Grid $grid
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Grid $grid)
    {
        $this->authorize('friendly', $grid->planet);

        if (!$grid->training) {
            return $this->createBadRequestJsonResponse();
        }

        DB::transaction(function () use ($grid) {
            $grid->training->cancel();
        });
    }

    /**
     * Authorize the trainer.
     *
     * @param Grid $grid
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function authorizeTrainer(Grid $grid)
    {
        $this->authorize('friendly', $grid->planet);

        if (!$grid->building_id) {
            return $this->createBadRequestJsonResponse();
        }

        if ($grid->building->type != Building::TYPE_TRAINER) {
            return $this->createBadRequestJsonResponse();
        }
    }
}
