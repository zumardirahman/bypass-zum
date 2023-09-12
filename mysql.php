<?php
$host = 'localhost';
$db   = 'target';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$pdo = new PDO($dsn, $user, $pass);

// Functions
function getDatabases() {
    global $pdo;
    $stmt = $pdo->query('SHOW DATABASES');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getTables($database) {
    global $pdo;
    $stmt = $pdo->query("SHOW TABLES FROM $database");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getFields($database, $table) {
    global $pdo;
    $stmt = $pdo->query("DESCRIBE $database.$table");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getRows($database, $table) {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM $database.$table");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUniqueField($database, $table) {
    global $pdo;
    $stmt = $pdo->query("SHOW KEYS FROM $database.$table WHERE Key_name = 'PRIMARY'");
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $keys[0]['Column_name'] ?? null;
}

function addRow($database, $table, $data) {
    global $pdo;
    
    $fields = implode(", ", array_keys($data));
    $placeholders = ":" . implode(", :", array_keys($data));

    $sql = "INSERT INTO $database.$table ($fields) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);

    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }

    return $stmt->execute();
}

function editRow($database, $table, $data, $uniqueField) {
    global $pdo;

    $setParts = [];
    foreach ($data as $key => $value) {
        if ($key != $uniqueField) {
            $setParts[] = "$key = :$key";
        }
    }
    $setString = implode(", ", $setParts);

    $sql = "UPDATE $database.$table SET $setString WHERE $uniqueField = :uniqueValue";
    $stmt = $pdo->prepare($sql);

    foreach ($data as $key => $value) {
        if ($key != $uniqueField) {
            $stmt->bindValue(":$key", $value);
        }
    }

    $stmt->bindValue(":uniqueValue", $data[$uniqueField]);

    return $stmt->execute();
}

function deleteRow($database, $table, $uniqueField, $uniqueValue) {
    global $pdo;

    $sql = "DELETE FROM $database.$table WHERE $uniqueField = :uniqueValue";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(":uniqueValue", $uniqueValue);

    return $stmt->execute();
}

// CRUD Operations
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add':
            $database = $_POST['database'];
            $table = $_POST['table'];
            $data = $_POST;
            unset($data['action'], $data['database'], $data['table']);
            addRow($database, $table, $data);
            break;
        case 'edit':
            $database = $_POST['database'];
            $table = $_POST['table'];
            $uniqueField = getUniqueField($database, $table);
            $data = $_POST;
            unset($data['action'], $data['database'], $data['table']);
            editRow($database, $table, $data, $uniqueField);
            break;
        case 'delete':
            $database = $_POST['database'];
            $table = $_POST['table'];
            $uniqueField = getUniqueField($database, $table);
            $uniqueValue = $_POST['uniqueValue'];
            deleteRow($database, $table, $uniqueField, $uniqueValue);
            break;
    }
}

// Display UI
echo "<h2>Select Database:</h2>";
echo "<select onchange=\"window.location = '?database='+this.value\">";
foreach (getDatabases() as $database) {
    echo "<option value='$database'";
    if (isset($_GET['database']) && $_GET['database'] == $database) {
        echo " selected";
    }
    echo ">$database</option>";
}
echo "</select>";

if (isset($_GET['database'])) {
    $selectedDatabase = $_GET['database'];
    $pdo->exec("USE $selectedDatabase");
    echo "<h2>Select Table:</h2>";
    echo "<select onchange=\"window.location = '?database=$selectedDatabase&table='+this.value\">";
    foreach (getTables($selectedDatabase) as $table) {
        echo "<option value='$table'";
        if (isset($_GET['table']) && $_GET['table'] == $table) {
            echo " selected";
        }
        echo ">$table</option>";
    }
    echo "</select>";

    if (isset($_GET['table'])) {
        $selectedTable = $_GET['table'];
        $rows = getRows($selectedDatabase, $selectedTable);
        $fields = getFields($selectedDatabase, $selectedTable);
        
        // Tampilkan tabel
        echo "<h2>Data from Table: $selectedTable</h2>";
        echo "<table border='1'>";
        echo "<thead>";
        echo "<tr>";
        foreach ($fields as $field) {
            echo "<th>$field</th>";
        }
        echo "<th>Action</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($rows as $row) {
            echo "<tr>";
            
            $uniqueField = getUniqueField($selectedDatabase, $selectedTable);
            $uniqueValue = $row[$uniqueField];
            
            foreach ($fields as $field) {
                echo "<td data-field='{$field}' data-unique='{$row[$uniqueField]}'>{$row[$field]}</td>";
            }
            
            echo "<td>";
            echo "<button onclick=\"editData('$uniqueValue')\">Edit</button>";
            echo "<button onclick=\"deleteData('$uniqueValue')\">Delete</button>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
    }
}
?>
<?php if (isset($_GET['table'])): ?>
<div id="editFormContainer" style="display: none;">
    <form id="editForm" method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="database" value="<?php echo $_GET['database']; ?>">
        <input type="hidden" name="table" value="<?php echo $_GET['table']; ?>">
        <input type="hidden" name="<?php echo getUniqueField($_GET['database'], $_GET['table']); ?>" id="editUniqueField">

        <?php foreach (getFields($_GET['database'], $_GET['table']) as $field): ?>
            <div>
                <label for="edit_<?php echo $field; ?>"><?php echo $field; ?>:</label>
                <input type="text" name="<?php echo $field; ?>" id="edit_<?php echo $field; ?>">
            </div>
        <?php endforeach; ?>

        <input type="submit" value="Update">
    </form>
</div>

<div id="deleteFormContainer" style="display: none;">
    <form id="deleteForm" method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="database" value="<?php echo $_GET['database']; ?>">
        <input type="hidden" name="table" value="<?php echo $_GET['table']; ?>">
        <input type="hidden" name="uniqueValue" id="deleteUniqueValue">
        <p>Are you sure you want to delete this record?</p>
        <input type="submit" value="Delete">
    </form>
</div>

<script>
function editData(uniqueValue) {
    const fields = <?php echo json_encode(getFields($_GET['database'], $_GET['table'])); ?>;
    for (const field of fields) {
        const cell = document.querySelector(`[data-field="${field}"][data-unique="${uniqueValue}"]`);
        const input = document.getElementById('edit_' + field);
        if (cell && input) {
            input.value = cell.innerText;
        }
    }
    document.getElementById('editUniqueField').value = uniqueValue;
    document.getElementById('editFormContainer').style.display = 'block';
}

function deleteData(uniqueValue) {
    if (confirm('Are you sure you want to delete this record?')) {
        document.getElementById('deleteUniqueValue').value = uniqueValue;
        document.getElementById('deleteForm').submit();
    }
}
</script>
<?php endif; ?>