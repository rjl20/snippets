<?php
# calfix
#
# A quick script to munge the event IDs of calendar events originally created in Google Calendar and then
# shared with an Office 365 user, who then shares their calendar back with a Google user. In this scenario,
# events may be missing from the O365 user's calendar when viewed by a Google user. I think it's something
# to do with event viewing permissions, but frankly I don't really care. Munging the event's UID makes them
# all show up properly.
#
# Josh Larios <jdlarios@uw.edu> - 2021.05.25

# To create the calfix.db sqlite3 db this uses as a data store:
# echo 'CREATE TABLE calfix(id integer primary key, hash varchar(64) unique, url text);' | sqlite3 calfix.db

# Set the URL of this installation here:
$base="https://whatever.com/calfix/";

$db = new SQLite3('calfix.db', SQLITE3_OPEN_READWRITE);

$owalink = urldecode($_GET['url']);
$ics = $_GET['ics'];
$ics = preg_replace('/[^0-9a-fA-F]/', '', $ics);

if ($ics) {
    # ics is a hash; look it up and get the original URL, munge and return it
    $sql = $db->prepare('SELECT url FROM calfix WHERE hash = :hash;');
    $sql->bindValue(':hash', $ics);
    $result = $sql->execute();
    while ($row = $result->fetchArray()) {
        $source = $row[url];
    }
    if ($source) {
        header('Content-type: text/calendar');
        header('Cache-Control: max-age=3600');
        $ics = file_get_contents($source);
        $ics = preg_replace('/^UID:(.*?)@google\.com/m', "UID:$1@google-fix", $ics);
        print $ics;
    }
} else {
    $source = preg_replace('/.*webcal:\/\/(.*)&.*/', 'https://$1', $owalink);
    if (preg_match('/^https:.*reachcalendar\.ics$/', $source)) { // hopefully avoid local file exposure
        # Store the url and hash in the database, return a link with the hash
        $hash = hash('sha256', $source);
        $sql = $db->prepare('INSERT INTO calfix (hash, url) VALUES(:hash, :url);');
        $sql->bindValue(':hash', $hash);
        $sql->bindValue(':url', $source);
        $result = $sql->execute();
        if($result==FALSE) {
            $sqlerr = $db->lastErrorMsg();
        }
  ?>
        <html><head></head><body>
<!-- <?= $sqlerr ?> -->
        <h1>O365 to Google Calendar</h1>
        <h2>Instructions (step 2):</h2>
        <ul>
        <li>Click the "Copy" button below.</li>
        <li>Open your Google Calendar, click on the "+" icon next to "Other calendars" and click "<a href="https://calendar.google.com/calendar/u/2/r/settings/addbyurl" target="_blank">From URL</a>"</li>
        <li>Paste the link from below into the URL field of the calendar-adding form, and click "Add calendar".</li>
        <li>Make sure to give the newly-added calendar an appropriate name; Google will name it after the URL rather than the name of the calendar.</li>
        </ul>
        <input id="urltocopy" type="text" size="120" readonly value="<?= $base ?>?ics=<?= $hash ?>"> <input type="button" value="Copy" onclick="copyURL()">
        <script>
        function copyURL() {
            var copyText = document.getElementById("urltocopy");
            copyText.select();
            copyText.setSelectionRange(0, 99999); 
            document.execCommand("copy");
        }
        </script>
        </body></html>
<?php
    } else { // No (or invalid) URL, show form for entering one
?>
        <html><head></head><body>
        <h1>O365 to Google Calendar</h1>
        <h2>Instructions (step 1):</h2>
        <ul>
        <li>Paste the O365 calendar link from the sharing request email into the field below.</li>
        <li>Click "Submit" to go to the next step.</li>
        </ul>
    <form method="GET">
    <input name="url"  type="text" size="80">
    <input type="submit">
    </form>
    </body>
    </html>
<?php
    }
}
