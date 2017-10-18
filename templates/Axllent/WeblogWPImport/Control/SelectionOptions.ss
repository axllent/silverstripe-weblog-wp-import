<!DOCTYPE html>
<html lang="en-US">
<head>
	<% base_tag %>
	<title>Import Options</title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
</head>
<body>
	<div class="container">
		<h1>WordPress Import Options</h1>
		<p><a href="wp-import/cancel/" class="button button-cancel">&laquo; Cancel</a></p>
		<h4>XML Statistics</h4>
		<% with $ImportData %>
			<table>
				<tr>
					<td>BaseURL:</td>
					<td>$SiteURL</td>
				</tr>
				<tr>
					<td>Posts:</td>
					<td>$Posts.Count published</td>
				</tr>
				<tr>
					<td>Categories:</td>
					<td>
						($Categories.Count)
						<% loop $Categories.Sort("Title") %>
							$Title
							<% if $Last %>
							<% else %>,
							<% end_if %>
						<% end_loop %>
					</td>
				</tr>
			</table>
		<% end_with %>

		<h4>Selected weblog: <b>$Blog.MenuTitle</b> ($Blog.Link)</h4> $OptionsForm

	</div>
	<script type="application/javascript">
		var form = document.querySelector('form');
		form.addEventListener('submit', function() {
			this.querySelector('input[type="submit"]')
				.setAttribute('disabled', 'disabled');
		}, false);
	</script>
</body>
</html>
