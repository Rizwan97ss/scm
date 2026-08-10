<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): JsonResponse
    {
        $query = trim($request->string('q')->toString());

        if (mb_strlen($query) < 2) {
            return ApiResponse::success(['query' => $query, 'results' => []]);
        }

        return ApiResponse::success(['query' => $query, 'results' => $search->search($query, $request->user())]);
    }
}
