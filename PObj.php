<pre>
<?
class PObj extends ArrayObject {
    private $name;
    private $path;
    private $pdo;

    private function initialize_db($db_location) {
        // Create the sqlite database for this object, or fetch the existing
        // database if one has already been initialized.
        echo "$db_location\n";
        $pdo = new PDO("$db_location");

        // Create the table thing
        // Each thing has a unique id, an optional key, and an optional value
        $create_query = "CREATE TABLE IF NOT EXISTS thing (
            id     INTEGER PRIMARY KEY AUTOINCREMENT,
            key    TEXT,
            value  TEXT,
            parent INTEGER
        );";
        $pdo->exec($create_query);
        return $pdo;
    }
    function __construct($name, $path = null, $pdo = null) {
        if(is_null($path)) {
            // This pObj is the root
            $path = array();
            $pdo = $this->initialize_db($name);
            $name = null;
        }
        $this->pdo = $pdo;
        $this->path = $path;
        if($name) $this->path[] = $name;
    }

    public function val() {
        $parent = null;
        foreach($this->path as $key) {
            $select_sql = "SELECT id, value FROM thing WHERE parent" .($parent ? "=:parent" : " IS NULL" ) ." AND key=:key;";
            $select_statement = $this->pdo->prepare($select_sql);
            $select_statement->bindParam(':key', $key);
            if($parent) $select_statement->bindParam(':parent', $parent);
            $select_statement->execute();
            $result = $select_statement->fetchAll(PDO::FETCH_ASSOC);
            if(count($result) == 0) return null;
            else $parent = $result[0]['id'];
        }
        $value = $result[0]["value"];
        if(!is_null($value)) {
            return $value;
        } else {
            $select_sql = "SELECT key FROM thing WHERE parent=:parent";
            $select_statement = $this->pdo->prepare($select_sql);
            $select_statement->bindParam(':parent', $parent);
            $select_statement->execute();
            $result = $select_statement->fetchAll(PDO::FETCH_ASSOC);
            $arr = array();
            foreach($result as $row) {
                $key = $row['key'];
                $arr[$key] = $this[$key]->val();
            }
            return $arr;
        }
    }

    private function get($name) {
        return new PObj($name, $this->path, $this->pdo);
    }

    // Todo: rename this
    private function createRowIfNotExists($path, $value) {
        $parent = null;
        foreach($path as $key) {
            $select_sql = "SELECT id FROM thing WHERE parent" .($parent ? "=:parent" : " IS NULL" ) ." AND key=:key;";
            $select_statement = $this->pdo->prepare($select_sql);
            $select_statement->bindParam(':key', $key);
            if($parent) $select_statement->bindParam(':parent', $parent);
            $select_statement->execute();
            $result = $select_statement->fetchAll(PDO::FETCH_ASSOC);
            if($result[0]['id']) $id = $result[0]['id'];
            if(count($result) == 0) {
                $insert_sql = "INSERT INTO thing (key, value, parent) values (:key, null, :parent);";
                $statement = $this->pdo->prepare($insert_sql);
                $statement->execute(array(':parent' => $parent, ':key' => $key));
                $id = $this->pdo->lastInsertId();
            }
            $parent = $id;
        }
        $update_sql = "UPDATE thing SET value=:value WHERE id=:id";
        $update_statement = $this->pdo->prepare($update_sql);
        $update_statement->bindParam(':id', $id);
        $update_statement->bindParam(':value', $value);
        $update_statement->execute();
    }

    private function set($name, $value) {
        if(!$name) {
            // Todo: If the name is null, set it to the lowest unoccupied number
            // i.e. it should have a similar behavior to sql's autoincrement feature.
            var_dump($name);
        }
        if(is_array($value)) {
            $values = $value;
            $sub = new PObj($name, $this->path, $this->pdo);
            foreach($values as $name => $value) {
                $sub[$name] = $value;
            }
        } else {
            $path = $this->path;
            $path[] = $name;
            $this->createRowIfNotExists($path, $value);
            $pobj = new PObj($name, $this->path, $this->pdo);
            echo implode('->', $pobj->path) . " = {$value};\n";
        }
    }

    function offsetGet($name) {
        return $this->get($name);
    }

    function offsetSet($name, $value) {
        $this->set($name, $value);
    }

}

$p = new PObj("sqlite:db/test.db");
//$p["a"]["b"]["c"] = "World";
$p["Applicants"][1]["FirstName"] = "Josh";
$p["Applicants"][2] = array(
    "FirstName" => "Richard",
    "LastName"  => "Feynman"
);
$p["Applicants"][] = array(
    "FirstName" => "Barack",
    "LastName" => "Obama"
);
echo "\n\n";
echo json_encode($p['Applicants']->val());
