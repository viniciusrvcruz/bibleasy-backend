<?php

namespace App\Http\Controllers;

use App\Actions\Book\GetBooksAction;
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
        $books = app(GetBooksAction::class)->execute($version);

        return BookResource::collection($books);
    }
}

