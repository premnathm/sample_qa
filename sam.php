<!DOCTYPE html>
<html>
<body>

<?php
function countLetters($matches) {
  return $matches[0] . '[' . strlen($matches[0]) . 'letter]';
}

function countDigits($matches) {
  return $matches[0] . '[' . strlen($matches[0]) . 'digit]';
}

$input = "365 days";
$patterns = [
  '/[a-z]+/i' => 'countLetters',
  '/[0-9]+/' => 'countDigits'
];
$result = preg_replace_callback_array($patterns, $input);
echo $result;
?>

</body>
</html>
