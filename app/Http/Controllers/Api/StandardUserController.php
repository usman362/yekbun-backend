<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreStandardUserRequest;
use App\Http\Requests\UpdateStandardUserRequest;

class StandardUserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

            $blockedUsers = User::where("level", 0)->where('is_admin_user', 0)->where('status', 0)->orderBy("updated_at", "DESC")->get();
            $users = User::where("level", 0)->where('is_admin_user', 0)->where('status','!=', 0)->orderBy("updated_at", "DESC")->get();
            return response()->json(['users' => $users, 'blocked_users' => $blockedUsers],200);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view("content.users.standard.create");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreStandardUserRequest $request)
    {
        $validated = $request->validated();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->image->store("/users", "public");
            $validated["image"] = $imagePath;
        }

        $validated['level'] = 0; // Add level of standard user

        $user = User::create($validated);

        return back()->with("success", "User successfully created.");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::find($id);
        return view("content.users.standard.show", compact("user"));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::find($id);
        return view("content.users.standard.edit", compact("user"));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function update(UpdateStandardUserRequest $request, $id)
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

        return back()->with("success", "User successfully updated");
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

        return back()->with("success", "User successfully deleted.");
    }

    public function block(Request $request, $id)
    {
        $user = User::find($id);
        $user->status = 0;
        $user->block_for_days = $request->block_for_days;
        $user->save();

        if ($request->block_for_days)
            return back()->with("success", "User blocked for {$request->block_for_days} days.");
        else
            return back()->with("success", "User blocked.");
    }

    public function warn(Request $request, $id)
    {
        $user = User::find($id);
        $user->is_warned = 1;
        $user->warning_cause = $request->warning_cause;
        $user->save();

        return back()->with("success", "User warned.");
    }

    public function upgrade(Request $request, $id)
    {

        if ($request->password !== '123456') {
            return back()->with("error", "Wrong password!");
        }
        $user = User::find($id);
        $user->level = (int) $request->level;
        $user->save();

        $levels = [
            0 => 'Standard',
            1 => 'Premium',
            2 => 'VIP'
        ];

        return back()->with("success", "User upgraded to {$levels[$request->level]}.");
    }
}
