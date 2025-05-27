<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        if ($request->ajax()) {
            $query = User::orderBy('id', 'desc');

            return DataTables::of($query)
                ->addColumn('action', function ($item) {
                    $authUser = auth()->user();

                    $isCurrentUser = $authUser->id === $item->id;
                    $isItemAdmin   = $item->user_type === 'admin';
                    $isAuthAdmin   = $authUser->user_type === 'admin';

                                                                 // ---- Edit Button Logic ----
                    $canEdit = ! (! $isAuthAdmin && $isItemAdmin); // can't edit admin if not admin

                    $editButton = '
        <li>
            <a class="dropdown-item ' . ($canEdit ? '' : 'disabled text-muted') . '"
               href="' . ($canEdit ? action('App\Http\Controllers\Admin\UserController@edit', $item->id) : '#') . '"
               style="' . ($canEdit ? '' : 'pointer-events: none;') . '"
               title="' . ($canEdit ? 'Edit user' : 'You cannot edit an admin account') . '">
                <i class="la la-edit"></i> Edit
            </a>
        </li>';

                    // ---- Delete Button Logic ----
                    $canDelete = ! $isCurrentUser && $isAuthAdmin;

                    $deleteButton = '
        <li>
            <a class="dropdown-item ' . ($canDelete ? 'delete-record' : 'disabled text-muted') . '"
               href="' . ($canDelete ? 'javascript:void(0);' : '#') . '"
               ' . ($canDelete ? 'data-href="' . action('App\Http\Controllers\Admin\UserController@destroy', $item->id) . '"' : '') . '
               style="' . ($canDelete ? '' : 'pointer-events: none;') . '"
               title="' . (! $canDelete ? ($isCurrentUser ? 'You cannot delete your own account' : 'Only admins can delete users') : 'Delete user') . '">
                <i class="la la-trash"></i> Delete
            </a>
        </li>';

                    return '
        <div class="btn-group">
            <div class="dropdown">
                <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Action
                </button>
                <div class="dropdown-menu">
                    ' . $editButton . '
                    ' . $deleteButton . '
                </div>
            </div>
        </div>';
                })

                ->editColumn('id', function ($item) {
                    static $i = 0;
                    return ++$i;
                })
                ->editColumn('name', fn($item) => ucwords($item->name))

                ->editColumn('last_login_at', function ($item) {
                    return $item->last_login_at
                    ? \Carbon\Carbon::parse($item->last_login_at)->diffForHumans()
                    : 'Never';
                })

                ->editColumn('user_type', function ($item) {
                    return match ($item->user_type) {
                        'admin' => 'Admin',
                        'manager' => 'Manager',
                        default   => ucfirst($item->user_type),
                    };
                })

                ->escapeColumns([])
                ->rawColumns(['action'])
                ->make();
        }

        $title = 'Users';

        return view('admin.users.index', compact('title'));

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $user_types = ['manager'];
        $title      = 'Create User';
        return view('admin.users.create', compact('user_types', 'title'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'name'      => 'required',
            'email'     => 'required|email|unique:users',
            'password'  => 'required',
            'user_type' => 'required',
        ];

        $this->validate($request, $rules);

        try {
            DB::beginTransaction();

            $user            = new User();
            $user->name      = $request->name;
            $user->email     = $request->email;
            $user->password  = Hash::make($request->password);
            $user->user_type = $request->user_type;
            $user->save();

            DB::commit();

            // Log activity
            activity()
                ->causedBy(auth()->user())
                ->event('created')
                ->performedOn($user)
                ->useLog('User')
                ->log('created a new user');

            return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('users.index')->with('error', 'Failed to create user.');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::findOrFail($id);

        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

        $user       = User::findOrFail($id);
        $user_types = ['admin', 'manager'];
        $title      = 'Edit User';

        return view('admin.users.edit', compact('user', 'user_types', 'title'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $rules = [
            'name'      => 'required',
            'email'     => 'required|email|unique:users,email,' . $id,
            'user_type' => 'required',
        ];

        $this->validate($request, $rules);

        try {
            DB::beginTransaction();

            $user            = User::findOrFail($id);
            $user->name      = $request->name;
            $user->email     = $request->email;
            $user->user_type = $request->user_type;
            $user->save();

            DB::commit();

            // Log activity
            activity()
                ->causedBy(auth()->user())
                ->event('updated')
                ->performedOn($user)
                ->useLog('User')
                ->log('updated a user');

            return redirect()->route('users.index')->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('users.index')->with('error', 'Failed to update user.');
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try
        {
            DB::beginTransaction();
            $user = User::findOrFail($id);
            $user->delete();

            DB::commit();

            // Log activity
            activity()
                ->causedBy(auth()->user())
                ->event('deleted')
                ->performedOn($user)
                ->useLog('User')
                ->log('deleted a user');

            $output = ['success' => true,
                'msg'                => 'User deleted successfully',
            ];

            return response()->json($output);
        } catch (\Exception $e) {
            DB::rollBack();
            $output = ['success' => false,
                'msg'                => 'Failed to delete user',
            ];
            return response()->json($output);
        }
    }
}
