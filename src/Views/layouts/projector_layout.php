<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= htmlspecialchars(url('/'), ENT_QUOTES, 'UTF-8') ?>">
    <title>Convota — Assembly Hall Display</title>
    <link rel="stylesheet" href="<?= url('/assets/css/projector.css') ?>">
</head>
<body class="projector-body">
    <?= $content ?>
    <script src="<?= url('/assets/js/projector.js') ?>"></script>
</body>

</html>
