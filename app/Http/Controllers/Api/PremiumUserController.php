<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePremiumUserRequest;
use App\Http\Requests\UpdatePremiumUserRequest;
use Illuminate\Support\Facades\Storage;

class PremiumUserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request()->limit) {
            return User::where("level", 1)
                        ->orderBy("updated_at", "DESC")
                        ->paginate(request()->limit)
                        ->appends(["limit" => request()->limit]);
        }

        $users = User::where("level", 1)->orderBy("updated_at", "DESC")->get();

        return [
            "data" => $users
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StorePremiumUserRequest $request)
    {
        $validated = $request->validated();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->image->store("/users", "public");
            $validated["image"] = $imagePath;
        }

        $validated['level'] = 1; // Add level of premium user

        $user = User::create($validated);

        return [
            "message" => "User successfully created.",
            "data" => $user
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
            "data" => User::find($id)
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatePremiumUserRequest $request, $id)
    {
        $validated = $request->validated();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->image->store("/users", "public");
            $validated["image"] = $imagePath;
        }

        $user = User::find($id);
        $user->fill($validated);
        $user->save();

        return [
            "message" => "User successfully updated.",
            "data" => $user
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
        $user = User::find($id);

        // Delete Image
        if ($user->image)
            Storage::delete($user->image);

        $user->delete();

        return [
            "message" => "User successfully deleted."
        ];
    }
}
