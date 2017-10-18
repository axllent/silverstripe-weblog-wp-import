<!DOCTYPE html>
<html lang="en-US">
<head>
	<% base_tag %>
	<title>Weblog WordPress Importer</title>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
</head>
<body>
	<div class="container">
		<h1>Weblog WordPress Import Tool</h1>
		<p>
			Upload your WordPress <b>blog post</b> <code>XML</code> export file. Import options will be presented on the next page.
		</p>
		$UploadForm
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
