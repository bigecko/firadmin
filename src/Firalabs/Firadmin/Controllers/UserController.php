<?php namespace Firalabs\Firadmin\Controllers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Response;
use Firalabs\Firadmin\Facades\AjaxRequest;
use Firalabs\Firadmin\Facades\Permissions;
use Firalabs\Firadmin\Repository\UserRepositoryInterface;
use Firalabs\Firadmin\Repository\UserRoleRepositoryInterface;
use Illuminate\Support\Facades\Auth;

/**
 * Controller used for users managment
 * 
 * @author maxime.beaudoin
 */
class UserController extends BaseController {

/**
	 * The current active menu URI
	 * 
	 * @var string
	 */
	public $active_menu = 'admin/user';
	
	/**
	 * User repository
	 * 
	 * @var UserRepositoryInterface
	 */
	protected $_users;
	
	/**
	 * Constructor
	 */
	public function __construct(UserRepositoryInterface $user)
    {    	    	
    	//Add csrf filter when posting forms
    	$this->beforeFilter('csrf', array('on' => array('post', 'put', 'delete')));
    	
    	//Create user object
    	$this->_users = $user;
    }

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{		
		//Check permission
		if(Permissions::isAllowed(Auth::user(), 'user', 'read') !== true){
			return Redirect::route('get admin/login')
				->with('reason', Lang::get('firadmin::admin.messages.insufficient-permission') . '<br>')
				->with('error', 1);
		}
		
		//Define the number of item we want to display per page
		$paginate = Input::get('take')?(int) Input::get('take'):Config::get('firadmin::paginate');
		
		//Check if it's a ajax request
		if(AjaxRequest::isAjax()){
			
			//Define the number of row we want to skip
			$skip = Input::get('page')?(Input::get('page')-1)*$paginate:0;
			
			//Return users list in json
			return Response::json($this->_users->with('roles')->skip($skip)->take($paginate)->get()->toArray());
		}
		
		//Define the layout content
		$this->layout->content = View::make('firadmin::users.index', array(
			'users' => $this->_users->with('roles')->paginate($paginate)->appends('take', $paginate)
		));
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{		
		//Check permission
		if(Permissions::isAllowed(Auth::user(), 'user', 'create') !== true){
			return Redirect::route('get admin/login')
				->with('reason', Lang::get('firadmin::admin.messages.insufficient-permission') . '<br>')
				->with('error', 1);
		}
		
		//If we have roles set from input
		Input::old('roles')
			? $selected_roles = Input::old('roles') //select them from input
			: $selected_roles = array(); //No roles selected, so just set a empty array
		
		//Define the layout content
		$this->layout->content = View::make('firadmin::users.create', array('selected_roles' => $selected_roles));
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{			
		//Check permission
		if(Permissions::isAllowed(Auth::user(), 'user', 'create') !== true){
			return Redirect::route('get admin/login')
			->with('reason', Lang::get('firadmin::admin.messages.insufficient-permission') . '<br>')
			->with('error', 1);
		}
				
		//Try saving the user in database
		if($this->_users->save()){
			
			//If we have roles
			if(Input::get('roles')){
				
				//For each role
				foreach (Input::get('roles') as $role) {						
				
					//Create role
					$roles = app()->make('Firalabs\Firadmin\Repository\UserRoleRepositoryInterface');		
					$roles->role = $role;		
			
					//Save the user role
					$this->_users->roles()->save($roles);
				}
			}
			
			//Returns a response in JSON format if it's an Ajax request
			if(AjaxRequest::isAjax()){
				return Response::json(array ('success' => Lang::get('firadmin::admin.store-success') ));
			}
	
			//Redirect with a success message
			return Redirect::to(Config::get('firadmin::route.user'))
				->with('success', Lang::get('firadmin::admin.store-success'));
			
		//Else, fail to save the user
		} else {	

			//Flash input to repopulate them in the form
			Input::flash();
			
			//Returns a response in JSON format if it's an Ajax request
			if(AjaxRequest::isAjax()){
				return Response::json(array(
					'error' => 1, 
					'reason' => $this->_users->errors()->all(':message') 
				));
			}
		
			//Redirect to the form with errors 
			return Redirect::to(Config::get('firadmin::route.user') . '/create')
				->with('reason', $this->_users->errors()->all(':message<br>'))
				->with('error', 1);
			
		}
	}

	/**
	 * Display the specified resource.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function show($id)
	{			
		//Check permission
		if(Permissions::isAllowed(Auth::user(), 'user', 'read') !== true){
			return Redirect::route('get admin/login')
				->with('reason', Lang::get('firadmin::admin.messages.insufficient-permission') . '<br>')
				->with('error', 1);
		}
		
		//Get the user in database
		$user = $this->_users->find($id);
		
		//If the user don't exist
		if(!$user){
			
			//Returns a response in JSON format if it's an Ajax request
			if(AjaxRequest::isAjax()){
				return Response::json(array(
					'error' => 1, 
					'reason' => Lang::get('firadmin::admin.messages.user-not-found') 
				));
			}
				
			//Redirect to the user index with errors
			return Redirect::to(Config::get('firadmin::route.user'))
				->with('reason', Lang::get('firadmin::admin.messages.user-not-found'))
				->with('error', 1);
			
		//Else, great the user exist !
		} else {
		
			//Check if it's a ajax request
			if(AjaxRequest::isAjax()){
				
				//Simply return the user data in JSON
				return Response::json($user->toArray());
			}
					
			//Define the layout content
			$this->layout->content = View::make('firadmin::users.show', array(
				'user' => $user
			));
			
		}
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function edit($id)
	{
		//Check permission
		if(Permissions::isAllowed(Auth::user(), 'user', 'update') !== true){
			return Redirect::route('get admin/login')
				->with('reason', Lang::get('firadmin::admin.messages.insufficient-permission') . '<br>')
				->with('error', 1);
		}	
		
		//Get the user data
		$user = $this->_users->find($id);
		
		//If the user don't exist
		if(!$user){
			
			//Returns a response in JSON format if it's an Ajax request
			if(AjaxRequest::isAjax()){
				return Response::json(array(
					'error' => 1, 
					'reason' => Lang::get('firadmin::admin.messages.user-not-found') 
				));
			}
				
			//Redirect to users index with errors
			return Redirect::to(Config::get('firadmin::route.user'))
				->with('reason', Lang::get('firadmin::admin.messages.user-not-found'))
				->with('error', 1);
			
		//Else the user exist great !
		} else {
		
			//If we have roles set from input
			Input::old('roles')
				? $selected_roles = Input::old('roles') //select them from input
				: $selected_roles = $user->getRoles(); //No roles selected, get them from the model			
			
			//Define the layout content
			$this->layout->content = View::make('firadmin::users.edit', array(
				'user' => $user,
				'selected_roles' => $selected_roles
			));
			
		}
	}

	/**
	 * Update the specified resource in storage.
	 * 
	 * @param int $id
	 * @return Response
	 */
	public function update($id)
	{
		//Check permission
		if(Permissions::isAllowed(Auth::user(), 'user', 'update') !== true){
			return Redirect::route('get admin/login')
				->with('reason', Lang::get('firadmin::admin.messages.insufficient-permission') . '<br>')
				->with('error', 1);
		}
			
		//Get the user in database
		$user = $this->_users->find($id);
		
		//If the user don't exist
		if(!$user){
			
			//Returns a response in JSON format if it's an Ajax request
			if(AjaxRequest::isAjax()){
				return Response::json(array(
					'error' => 1, 
					'reason' => Lang::get('firadmin::admin.messages.user-not-found') 
				));
			}
				
			//Redirect to user index with errors
			return Redirect::to(Config::get('firadmin::route.user'))
				->with('reason', Lang::get('firadmin::admin.messages.user-not-found'))
				->with('error', 1);
			
		//Else, great the user exist !!
		} else {
			
			//Define custom validation rules because we don't want to validate same field then user store
			$rules = array(
			    'username' => 'required|min:5|unique:users',
			    'email' => 'required|email|unique:users',
		    );
			
			//Check if the username have changed
			if($user->username == Input::get ('username')){
				
				//The username still the same so replace the validation rules
				$rules['username'] = 'required|min:5';
				
			}
			
			//Check if the email have changed
			if($user->email == Input::get ('email')){
				
				//The email still the same so replace the validation rules
				$rules['email'] = 'required|email';
				
			}
				
			//Update user data
			$user->username = Input::get('username');
			$user->email = Input::get('email');
			
			//Just before save, we don't want to auto hash the existing password, replace this later if possible
			$user->autoHashPasswordAttributes = false;		
				
			//Try to save the user
			if($user->save($rules)){			
				
				//Delete the user roles
				$user->roles()->delete();		
				
				//If we have roles define for the user
				if(Input::get('roles')){
					
					//For each roles
					foreach (Input::get('roles') as $role) {
						
						//Create the role object
						$roles = app()->make('Firalabs\Firadmin\Repository\UserRoleRepositoryInterface');		
						$roles->role = $role;		
				
						//Save the user role
						$user->roles()->save($roles);
					}
				}	

				//Returns a response in JSON format if it's an Ajax request
				if(AjaxRequest::isAjax()){
					return Response::json(array ('success' => Lang::get('firadmin::admin.update-success') ));
				}
		
				//Redirect to index with success message
				return Redirect::to(Config::get('firadmin::route.user'))
					->with('success', Lang::get('firadmin::admin.update-success'));
				
			//Else, save validation fail
			} else {
	
				//Flash input to repopulate them in the form
				Input::flash();	

				//Returns a response in JSON format if it's an Ajax request
				if(AjaxRequest::isAjax()){
					return Response::json(array(
						'error' => 1, 
						'reason' => $user->errors()->all(':message') 
					));
				}
			
				//Redirect to the form with errors
				return Redirect::to(Config::get('firadmin::route.user') . '/' . $id . '/edit')
					->with('reason', $user->errors()->all(':message<br>'))
					->with('error', 1);
				
			}
		}
	}

	/**
	 * Change the user password
	 *
	 * @param int $id
	 * @return Response
	 */
	public function changePassword($id)
	{
		//Check permission
		if(Permissions::isAllowed(Auth::user(), 'user', 'update') !== true){
			return Redirect::route('get admin/login')
				->with('reason', Lang::get('firadmin::admin.messages.insufficient-permission') . '<br>')
				->with('error', 1);
		}
			
		//Get the user in database
		$user = $this->_users->find($id);
		
		//If the user don't exist
		if(!$user){
			
			//Returns a response in JSON format if it's an Ajax request
			if(AjaxRequest::isAjax()){
				return Response::json(array(
					'error' => 1, 
					'reason' => Lang::get('firadmin::admin.messages.user-not-found') 
				));
			}
				
			//Redirect to user index with error
			return Redirect::to(Config::get('firadmin::route.user'))
				->with('reason', Lang::get('firadmin::admin.messages.user-not-found'))
				->with('error', 1);
			
		// Else, great the user exist !!!
		} else {
		
			//Define custom validation rules because we don't want to validate same field then user store
			$rules = array(
		    	'password' => 'required|min:5',
		    	'password_confirmation' => 'required|min:5|same:password',
		    );
				
			//Update user
			$user->password = Input::get('password');
			$user->password_confirmation = Input::get('password_confirmation');
			
			//Try to save the user
			if($user->save($rules)){
				
				//Returns a response in JSON format if it's an Ajax request
				if(AjaxRequest::isAjax()){
					return Response::json(array ('success' => Lang::get('firadmin::admin.update-password-success') ));
				}
		
				//Redirect to user index with success message
				return Redirect::to(Config::get('firadmin::route.user'))
					->with('success', Lang::get('firadmin::admin.update-password-success'));
				
			//Else, save validation fail
			} else {
	
				//Flash input to repopulate them in the form
				Input::flash();
				
				//Returns a response in JSON format if it's an Ajax request
				if( AjaxRequest::isAjax()){
					return Response::json(array(
						'error' => 1, 
						'reason' => $user->errors()->all(':message') 
					));
				}
				
				//Redirect to the form with errors
				return Redirect::to(Config::get('firadmin::route.user') . '/' . $id . '/edit#change-password')
					->with('reason', $user->errors()->all(':message<br>'))
					->with('error', 1);
				
			}
		}
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//Check permission
		if(Permissions::isAllowed(Auth::user(), 'user', 'delete') !== true){
			return Redirect::route('get admin/login')
				->with('reason', Lang::get('firadmin::admin.messages.insufficient-permission') . '<br>')
				->with('error', 1);
		}
		
		//Get the user in database
		$user = $this->_users->find($id);
		
		//If the user don't exist
		if(!$user){
				
			//Returns a response in JSON format if it's an Ajax request
			if( AjaxRequest::isAjax() ){
				return Response::json(array (
					'error' => 1, 
					'reason' => Lang::get('firadmin::admin.destroy-fail') 
				));
			}
				
			//Redirect to user index with errors
			return Redirect::to(Config::get('firadmin::route.user'))
				->with('reason', Lang::get('firadmin::admin.destroy-fail'))
				->with('error', 1);
			
		//Else, great the user exist !!
		} else {		
			
			//Delete the user roles
			$user->roles()->delete();
			
			//Delete the user
			$user->delete($id);	

			//Returns a response in JSON format if it's an Ajax request
			if( AjaxRequest::isAjax()){
				return Response::json(array ('success' => Lang::get('firadmin::admin.destroy-success') ));
			}
			
			//Redirect to the user index with success message
			return Redirect::to(Config::get('firadmin::route.user'))
				->with('success', Lang::get('firadmin::admin.destroy-success'));
		}
	}
}