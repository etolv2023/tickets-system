<?php

namespace App\Http\Controllers;

use App\Models\GithubRepository;
use App\Models\GithubUnmatchedBranch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GithubUnmatchedBranchController extends Controller
{
    public function index(Request $request): View
    {
        $branches = GithubUnmatchedBranch::query()
            ->with(['repository:id,name,owner,repo', 'author:id,name,github_login,avatar_path'])
            ->when($request->filled('repo'), fn ($q) => $q->where('github_repository_id', $request->integer('repo')))
            ->when($request->filled('author'), fn ($q) => $q->where('author_login', 'like', '%' . $request->string('author')->trim() . '%'))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->string('q')->trim() . '%'))
            ->where('state', $request->input('state', 'active'))
            ->orderByDesc('last_commit_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('github.unmatched', [
            'branches' => $branches,
            'repositories' => GithubRepository::activeList(),
        ]);
    }
}
