<?php

	namespace vps\tools\modules\user\controllers;

	use app\base\Controller;
	use vps\tools\auth\AuthAction;
	use vps\tools\helpers\StringHelper;
	use vps\tools\helpers\TimeHelper;
	use vps\tools\helpers\Url;
	use vps\tools\modules\log\dictionaries\LogType;
	use vps\tools\modules\log\models\Log;
	use vps\tools\modules\user\models\User;
	use Yii;
	use yii\data\ActiveDataProvider;
	use yii\filters\AccessControl;

	class UserController extends Controller
	{
		public function actions ()
		{
			return [
				'auth' => [
					'class'           => AuthAction::className(),
					'successCallback' => [ $this, 'successAuth' ],
					'cancelUrl'       => Url::toRoute([ 'user/cancel' ])
				]
			];
		}

		public function behaviors ()
		{
			return [
				'access' => [
					'class' => AccessControl::className(),
					'rules' => [
						[ 'allow' => true, 'actions' => [ 'auth', 'login', 'cancel' ], 'roles' => [ '?' ] ],
						[
							'allow'        => false,
							'actions'      => [ 'auth', 'login', 'cancel' ],
							'roles'        => [ '@' ],
							'denyCallback' => function ($rule, $action) {
								if ($action->id === 'auth' && Yii::$app->request->cookies->has('returnUrl'))
								{
									$url = Yii::$app->request->cookies->getValue('returnUrl', Url::toRoute([ '/site/index' ]));
									Yii::$app->response->cookies->remove('returnUrl');
									$this->redirect($url);
								}
								Yii::$app->notification->errorToSession(Yii::tr('You are already logged in.', [], 'user'));
								$this->redirect(Url::toRoute([ '/user/index' ]));
							}
						],
						[ 'allow' => true, 'actions' => [ 'index', 'logout', 'view' ], 'roles' => [ '@' ] ],
						[
							'allow'         => true,
							'actions'       => [ 'manage', 'delete', 'temp-login' ],
							'roles'         => [ '@' ],
							'matchCallback' => function ($rule, $action) {
								if (!Yii::$app->user->identity->active)
								{
									Yii::$app->notification->errorToSession(Yii::tr('Your account is not approved yet.', [], 'user'));
									$action->controller->redirect(Url::toRoute([ '/site/index' ]));
								}
								elseif (!( Yii::$app->user->can('admin') or Yii::$app->user->can('admin_user') ))
								{
									Yii::$app->notification->errorToSession(Yii::$app->settings->get('text_auth_not_allowed', Yii::tr('You have no permissions.', [], 'user')));
									$action->controller->redirect(Url::toRoute([ '/site/index' ]));
								}

								return true;
							}
						],

					],
				],
			];
		}

		public function actionDelete ($id)
		{
			if (Yii::$app->user->can(User::R_ADMIN))
			{
				$userClass = $this->module->modelUser;
				if ($id != Yii::$app->user->id)
				{
					$user = $userClass::findOne($id);
					$user->delete();
				}
			}

			$this->redirect(Yii::$app->request->referrer);
		}

		public function actionIndex ()
		{
			$this->title = Yii::tr('User', [], 'user');
			$this->_tpl = '@userViews/index';
			$userClass = $this->module->modelUser;
			$user = $userClass::findOne(Yii::$app->user->id);
			$this->data('user', $user);
		}

		public function actionManage ()
		{
			$this->title = Yii::tr('User manage', [], 'user');
			$this->_tpl = '@userViews/manage';
		}

		public function actionCancel ()
		{
			$collection = Yii::$app->authClientCollection;
			$client = $collection->getClient(Yii::$app->session->get('client'));
			Yii::$app->notification->errorToSession(Yii::tr('You have decline the authorization via {client}.', [ 'client' => $client->title ], 'user'));
			$this->redirect(Url::toRoute([ '/user/login' ]));
		}

		public function actionLogin ()
		{
			$this->title = Yii::tr('Login', [], 'user');
			$this->_tpl = '@userViews/login';
			$defaultClient = Yii::$app->settings->get('auth_client_default', $this->module->defaultClient);
			if (!Yii::$app->request->cookies->has('returnUrl'))
			{
				$url = !empty(Yii::$app->request->referer) ? Yii::$app->request->referrer : Url::toRoute('/site/index');
				Yii::$app->response->cookies->add(new \yii\web\Cookie([
					'name'  => 'returnUrl',
					'value' => $url,
				]));
			}

			$this->data('defaultClient', $defaultClient);
		}

		public function actionTempLogin ($id)
		{
			$user = User::findOne([ 'id' => $id ]);
			if ($user !== null)
			{
				Yii::$app->session->set('isTempUser', true);
				Yii::$app->session->set('originalUserID', Yii::$app->user->id);
				Yii::$app->user->switchIdentity($user);
			}

			$this->redirect('/');
		}

		public function actionLogout ()
		{
			if (Yii::$app->session->get('isTempUser'))
			{
				$originalUserId = Yii::$app->session->get('originalUserID');
				if ($originalUserId !== null)
				{
					$user = User::findOne([ 'id' => $originalUserId ]);
					if ($user !== null)
					{
						Yii::$app->user->switchIdentity($user);
						Yii::$app->session->remove('isTempUser');
						Yii::$app->session->remove('originalUserID');

						$this->redirect('/');
					}
				}
			}

			$this->_tpl = '@userViews/logout';
			$referrer = Yii::$app->getRequest()->getReferrer();
			Yii::$app->user->logout();
			if ($this->module->redirectAfterLogout)
			{
				foreach ($this->module->guestRestrictedRoutes as $url)
				{
					if (StringHelper::pos($referrer, Url::toRoute([ $url ])))
					{
						$this->redirect(Yii::$app->user->returnUrl);
						Yii::$app->end();
					}
				}
			}
			else
			{
				$this->redirect(Yii::$app->user->returnUrl);
				Yii::$app->end();
			}

			$this->redirect($referrer);
			Yii::$app->end();
		}

		public function actionView ($id)
		{
			$this->_tpl = '@userViews/index';
			$userClass = $this->module->modelUser;
			$user = $userClass::findOne($id);
			if ($user == null)
			{
				Yii::$app->notification->errorToSession(Yii::tr('Given user does not exist.', [], 'user'));
				$this->redirect(Url::toRoute([ 'user/index' ]));
			}
			$this->title = $user->name;
			$get = Yii::$app->request->get();

			$query = Log::find();
			if (isset($get[ 'type' ]))
			{
				$query->andWhere([ 'type' => $get[ 'type' ] ]);
				$this->data('type', $get[ 'type' ]);
			}
			else
				$this->data('type', '');

			if (isset($get[ 'from' ]))
			{
				$query->andWhere([ '>=', 'dt', $get[ 'from' ] ]);
				$this->data('from', $get[ 'from' ]);
			}

			if (isset($get[ 'to' ]))
			{
				$query->andWhere([ '>=', 'dt', $get[ 'to' ] ]);
				$this->data('to', $get[ 'to' ]);
			}

			if (isset($get[ 'search' ]))
			{
				$query->andWhere([ 'or', [ 'like', 'email', $get[ 'search' ] ], [ 'like', 'action', $get[ 'search' ] ], [ 'like', 'url', $get[ 'search' ] ] ]);
				$this->data('search', $get[ 'search' ]);
			}
			$query->andWhere([ 'userID' => $user->id ]);
			$provider = new ActiveDataProvider([
				'query'      => $query,
				'sort'       => [
					'attributes'   => [
						'userID',
						'email',
						'type',
						'action',
						'url',
						'dt'
					],
					'defaultOrder' => [
						'dt' => SORT_DESC
					]
				],
				'pagination' => [
					'pageSize'       => Yii::$app->settings->get('page_size_object', 20),
					'forcePageParam' => false,
					'pageSizeParam'  => false,
					'urlManager'     => new \yii\web\UrlManager([
						'enablePrettyUrl' => true,
						'showScriptName'  => false,
					])
				]
			]);

			$this->data('models', $provider->models);
			$this->data('pagination', $provider->pagination);
			$this->data('sort', $provider->sort);

			$this->data('types', [ LogType::INFO, LogType::WARNING, LogType::ERROR ]);
			$this->data('user', $user);
		}

		/**
		 * @param \yii\authclient\BaseClient $client
		 *
		 * @throws \Exception
		 */
		public function successAuth ($client)
		{
			if (method_exists($client, 'successAuth'))
			{
				$user = $client->successAuth();
			}
			else
			{

				$userClass = $this->module->modelUser;
				$attributes = $client->getUserAttributes();

				if (is_array($attributes) and isset($attributes[ 'email' ]))
				{
					/** @var User $user */
					$user = $userClass::find()
						->where([ 'profile' => $attributes[ 'profile' ] ])
						->one();

					if ($user == null)
					{
						$user = $userClass::find()
							->where([ 'email' => $attributes[ 'email' ] ])
							->one();
					}

					if ($user == null)
					{
						$user = new $userClass;
						$user->register($attributes[ 'name' ], $attributes[ 'email' ], $attributes[ 'profile' ], $this->module->autoactivate);

						if (!empty($attributes[ 'roles' ]))
						{
							$user->assignRoles($attributes[ 'roles' ]);
							if (is_array($attributes[ 'roles' ]) and in_array(User::R_ADMIN, $attributes[ 'roles' ]))
								$user->active = 1;
						}
						else
							$user->assignRole($this->module->defaultRole);
					}

					if (!empty($attributes[ 'image' ]) and $user->image != $attributes[ 'image' ])
					{
						$user->image = $attributes[ 'image' ];
					}
					$user->save();
				}
			}
			if ($user == null or !isset($user->id))
				throw new \Exception(Yii::tr('Authorization failed.', [], 'user'));
			elseif ($user->active === 0)
			{

				Yii::$app->notification->errorToSession(Yii::tr('Your account is not approved yet.', [], 'user'));
			}
			else
			{
				$user->loginDT = TimeHelper::now();
				$user->save();

				if (Yii::$app->settings->get($this->module->durationSetting))
					$duration = Yii::$app->settings->get($this->module->durationSetting);
				else
					$duration = $this->module->duration;

				Yii::$app->user->login($user, $duration);

			}
			$cookies = Yii::$app->request->cookies;
			$url = $cookies->getValue('returnUrl', Url::toRoute([ '/site/index' ]));
			Yii::$app->response->cookies->remove('returnUrl');

			if ($this->module->redirectAfterLogin)
				$this->redirect($url);
			else
				$this->redirect(Url::toRoute([ '/site/index' ]));

			Yii::$app->end();
		}
	}
