<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Queries\FormTemplateQuery;
use Inertia\Inertia;
use Inertia\Response;

class FormTokenController extends Controller
{
    public function __invoke(FormTemplateQuery $query): Response
    {
        return Inertia::render('forms/Tokens', [
            'tokens' => $query->tokenGroups(),
        ]);
    }
}
