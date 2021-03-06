<div class="row user-infos">
	<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 col">
		<div class="panel panel-primary">
			<div class="row">
				<div class="col-md-3 col-lg-3 hidden-xs hidden-sm">
					{Html::img($user->image,[ 'class'=>'img-circle tools-user-image'])}
				</div>
				<div class=" col-md-9 col-lg-9 hidden-xs hidden-sm">
					<table class="table table-hover table-user-information">
						<tbody>
							<tr>
								<td>{Yii::tr('ID', [], 'user')}</td>
								<td>{$user->id}</td>
							</tr>
							<tr>
								<td>{Yii::tr('Name', [], 'user')}</td>
								<td>{$user->name}</td>
							</tr>
							<tr>
								<td>{Yii::tr('Profile', [], 'user')}</td>
								<td>{$user->profile}</td>
							</tr>
							<tr>
								<td>{Yii::tr('Email', [], 'user')}</td>
								<td>{Html::mailto($user->email)}</td>
							</tr>
							<tr>
								<td>{Yii::tr('Active', [], 'user')}</td>
								<td>{if $user['active']}
										{Html::fa('check',['id'=>"btn{$user['id']}",'class'=>'text-default','title'=>Yii::tr('Disable', [], 'user')])}
									{else}
										{Html::fa('ban',['id'=>"btn{$user['id']}",'class'=>'text-default','title'=>Yii::tr('Enable', [], 'user')])}
									{/if}
								</td>
							</tr>
							<tr>
								<td>{Yii::tr('Login Dt', [], 'user')}</td>
								<td>{Yii::$app->formatter->asDatetime($user->loginDT)}</td>
							</tr>
							<tr>
								<td>{Yii::tr('ActiveDT', [], 'user')}</td>
								<td>{Yii::$app->formatter->asDatetime($user->activeDT)}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
{if isset($models) and count($models)>0}
	{include file='@logViews/list.tpl'}
	{include file='@logViews/filterjs.tpl' url='/user/view'}
{/if}