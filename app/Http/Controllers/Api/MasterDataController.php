<?php

namespace App\Http\Controllers\Api;

use App\Commission\MasterData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MasterDataController extends Controller
{
    public function __invoke(MasterData $masters): JsonResponse
    {
        return response()->json(['data' => $masters->all()]);
    }
}
