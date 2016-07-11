<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="utf-8" />
    <title>Web Conferencing</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="s/bootstrap.min.css" rel="stylesheet" media="screen">
    <link href="s/bootstrap-theme.min.css" rel="stylesheet" media="screen">
</head>
<body>

    <div class="navbar">
    <div class="navbar-inner">
    <a class="brand" href="http://www.bigbluebutton.org">BigBlueButton</a>
    <ul class="nav pull-right">
    <li><span title="{$userId}">{$userDisplayName}</span></li>
    </ul>
    </div>
    </div>

	<div class="container">

	{if $notification != NULL}
	<div class="notification">{$notification}</div>
	{/if}

	<h3>Running Conferences</h3>

	<table class="table table-striped table-bordered">
		<thead>
		<tr>
			<th>Name</th>
			<th>Moderator</th>
			<th>Running</th>
			<th>Number of Participants</th>
			<th>Actions</th>
		</tr>
		</thead>
		<tbody>
		{if empty($conferences) }
			<tr><td colspan="5"><small class="text-info">No conferences in progress...</small></td></tr>
		{/if}
		{foreach $conferences as $confId => $confInfo}
		<tr>
			<td>{$confInfo['name']}</td>
			<td>{$confInfo['moderatorDN']}</td>
			<td>{$confInfo['isRunning']}</td>
			<td>{$confInfo['participantCount']}</td>
			<td>
				<form method="POST">
					<input type="hidden" name="action" value="join"> <input
						type="hidden" name="id" value="{$confId}"> <input type="submit" class="btn btn-primary"
						value="Join">
				</form> {if $confInfo['moderator'] === $userId}
				<form method="POST">
					<input type="hidden" name="action" value="end"> <input
						type="hidden" name="id" value="{$confId}"> <input type="submit"
						value="End" class="btn btn-danger">
				</form> {/if}</td>
		</tr>
		{/foreach}
		</tbody>
	</table>
{if !$restricted}
	<h3>Create New Conference</h3>

	<form class="form-horizontal" method="POST">
		<input type="hidden" name="action" value="create">

<div class="control-group">
<label class="control-label" for="name">Room Name</label>
<div class="controls">
<input class="input-xxlarge" type="text" name="name" id="name" value="My Conference" placeholder="Room Name">
</div>
</div>

<div class="control-group">
<label class="control-label" for="welcome">Welcome Message</label>
<div class="controls">
<textarea rows="5" class="input-xxlarge" name="welcome" id="welcome">Welcome to the conference "%%CONFNAME%%"!</textarea>
</div>
</div>

<div class="control-group">
<label class="control-label">Invite Teams &amp; Groups<br><a href="https://teams.surfconext.nl/Shibboleth.sso/Login?target=https%3A%2F%2Fteams.surfconext.nl%2Fteams%2Faddteam.shtml%3Fview%3Dapp" target="_blank">
Create New Team</a></label>
<div class="controls">

<div class="well well-small">
	<ul>
		<li><strong class="text-warning">If you do not select a group or team noone will be able to join the conference (including you!)</strong></li>
		<li>Group/Team members of selected groups will see the conference you create listed under "Running Conferences"</li>
		<li>Conferences will be deleted automatically if they are not used for a while. You can just create a new conference in that case</li>
	</ul>
</div>

{if empty($userGroups)}
    <small class="text-info">You are not a member of any group. Please join a group first...</small>
{else}
    {foreach $userGroups as $k => $v}
	    <label class="checkbox">
		    <input name="groups[]" type="checkbox" value="{$k}">{$v}
	    </label>
    {/foreach}
{/if}
</div>
</div>

<div class="control-group">
	<div class="controls">
        {if empty($userGroups)}
        		<input type="submit" disabled="disabled" class="btn btn-primary disabled" value="Create Conference">
        {else}
        		<input type="submit" class="btn btn-primary" value="Create Conference">
        {/if}
	</div>
</div>

</form>
{/if}
</div>
</body>
</html>
