<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFlaggedUserRequest;
use App\Http\Requests\UpdateFlaggedUserRequest;
use App\Models\FlaggedUser;
use Illuminate\Http\Request;

class FlaggedUserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request()->limit) {
            return FlaggedUser::orderBy("updated_at", "DESC")
                        ->paginate(request()->limit)
                        ->appends(["limit" => request()->limit]);
        }

        $flaggedUsers = FlaggedUser::orderBy("updated_at", "DESC")->get();

        return [
            "data" => $flaggedUsers
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreFlaggedUserRequest $request)
    {
        $validated = $request->validated();

        $flaggedUser = FlaggedUser::create($validated);

        return [
            "message" => "Flagged user successfully created.",
            "data" => $flaggedUser
        ];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return [
            "data" => FlaggedUser::find($id)
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateFlaggedUserRequest $request, $id)
    {
        $validated = $request->validated();

        $flaggedUser = FlaggedUser::find($id);
        $flaggedUser->fill($validated);
        $flaggedUser->save();

        return [
            "message" => "Flagged user successfully updated.",
            "data" => $flaggedUser
        ];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $flaggedUser = FlaggedUser::find($id);
        $flaggedUser->delete();

        return [
            "message" => "Flagged user successfully deleted."
        ];
    }
}
