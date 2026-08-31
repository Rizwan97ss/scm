<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $this->authorize('export', User::class);

        $query = User::query()
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($q2) => $q2->where('name', $request->string('role')->toString())))
            ->when($request->filled('ids'), fn ($q) => $q->whereIn('id', explode(',', $request->string('ids')->toString())));

        return (new UsersExport($query))->download('staff.xlsx');
    }
}
