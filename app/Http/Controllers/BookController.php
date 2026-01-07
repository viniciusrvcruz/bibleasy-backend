<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Version;

class BookController extends Controller
{
    /**
     * Display a listing of books for a specific version.
     */
    public function index(Version $version)
    {
        $books = $version->books()
            ->with(['chapters' => function ($query) {
                $query->withCount('verses')
                    ->orderBy('number');
            }])
            ->orderBy('order')
            ->get();

        return BookResource::collection($books);
    }
}

