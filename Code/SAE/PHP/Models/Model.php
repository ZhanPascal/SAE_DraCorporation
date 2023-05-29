<?php

class Model
{

    private $bd;

    private $omdbApi;

    private static $instance = null;

    private function __construct()
    {
        include_once("credentials.php");
        $this->bd = new PDO($dsn, $login, $mdp);
        $this->bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->bd->query("SET nameS 'utf8'");
    }

    public static function getModel()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // MyDataBase
    public function getPersonneByTconst($tconst)
    {
        $req = $this->bd->prepare('SELECT DISTINCT nconst FROM titleprincipals where tconst= :tconst');
        $req->bindValue(":tconst", $tconst);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTitreByNconst($nconst)
    {
        $req = $this->bd->prepare('SELECT DISTINCT tconst FROM titleprincipals where nconst= :nconst');
        $req->bindValue(":nconst", $nconst);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActorInformationByNconst($nconst)
    {
        $req = $this->bd->prepare('SELECT * FROM namebasics where nconst= :nconst');
        $req->bindValue(':nconst', $nconst);
        $req->execute();
        return $req->fetchall();
    }

    public function getMovieInformationByTconst($tconst)
    {
        $req = $this->bd->prepare("SELECT * FROM titlebasics where tconst= :tconst");
        $req->bindValue(":tconst", $tconst);
        $req->execute();
        return $req->fetchall();
    }

    public function getMovieAndRatingInformationByTconst($tconst)
    {
        $req = $this->bd->prepare("SELECT * FROM titlebasics JOIN titleratings ON titlebasics.tconst=titleratings.tconst where titlebasics.tconst= :tconst");
        $req->bindValue(":tconst", $tconst);
        $req->execute();
        return $req->fetchall();
    }

    public function getTitreAndRatingInformationByTconst($tconst)
    {
        $req = $this->bd->prepare("SELECT * FROM titlebasics LEFT JOIN titleratings ON titlebasics.tconst=titleratings.tconst where titlebasics.tconst= :tconst");
        $req->bindValue(":tconst", $tconst);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }


    // OMDB API
    public function getOmdbDescription(String $tconst)
    {
        $getContentsOmdb = file_get_contents('http://www.omdbapi.com/?i=' . $tconst . '&plot=full&apikey=' . $this->omdbApi);
        $omdb = json_decode($getContentsOmdb, TRUE);

        if ($omdb != null) {

            if ($omdb["Plot"] == "N/A") {
                return "Description non disponible";
            } else {
                return $omdb["Plot"];
            }
        }
    }

    public function getOmdbAwards(String $tconst)
    {
        $getContentsOmdb = file_get_contents('http://www.omdbapi.com/?i=' . $tconst . '&plot=full&apikey=' . $this->omdbApi);
        $omdb = json_decode($getContentsOmdb, TRUE);

        if ($omdb != null) {

            if ($omdb["Awards"] == "N/A") {
                return "Ce filmes n'a pas eu d'Awards";
            } else {
                return $omdb["Awards"];
            }
        }
    }

    public function getOmdbPoster(String $tconst)
    {
        try {
            $getContentsOmdb = file_get_contents('http://www.omdbapi.com/?i=' . $tconst . '&plot=full&apikey=' . $this->omdbApi);
            $omdb = json_decode($getContentsOmdb, TRUE);

            if ($omdb != null) {

                if ($omdb["Poster"] == "N/A") {
                    return "Content/img/NoImageAvailable.png";
                } else {
                    return $omdb["Poster"];
                }
            }
        } catch (Exception $e) {
            return "Content/img/NoImageAvailable.png";
        }
    }


    //Wikipedia affiche
    public function getActorPoster(String $nconst)
    {
        try {
            $id = $this->bd->prepare("SELECT nconst, lien FROM afficheacteurs WHERE nconst= :nconst");
            $id->execute(['nconst' => $nconst]);

            if ($id->rowCount() > 0) {
                $lien = $id->fetch();
                if ($lien['lien'] != "") {
                    return $lien['lien'];
                } else {
                    return "Content/img/NoPictureAvailable.png";
                }
            } else {
                $pnconst = array("nconst" => $nconst);
                $json = json_encode($pnconst);
                file_put_contents("/home/DraCorporation/public_html/Content/json/" . $nconst . ".json", $json);

                $command = "/usr/bin/python3 /home/DraCorporation/public_html/Content/json/python/AfficheActeurs.py 2>&1";

                try {
                    exec($command, $output, $status);
                    /*if ($status !== 0) {
                        echo "Erreur lors de l'exécution de la commande: $command";
                        var_dump($output);
                        exit();
                    }//*/
                } catch (Exception $e) {
                    echo 'Erreur lors de l\'exécution du script Python : ',  $e->getMessage(), "\n";
                }
                if (file_exists("/home/DraCorporation/public_html/Content/json/" . $nconst . '_resultat' . '.json')) {
                    $reponse = file_get_contents("/home/DraCorporation/public_html/Content/json/" . $nconst . '_resultat' . '.json');
                    $resultat = str_replace(array('[', ']', '"'), '', $reponse);

                    if (file_exists("/home/DraCorporation/public_html/Content/json/" . $nconst . ".json")) {
                        unlink("/home/DraCorporation/public_html/Content/json/" . $nconst . '.json');
                    }
                    if (file_exists("/home/DraCorporation/public_html/Content/json/" . $nconst . '_resultat' . '.json')) {
                        unlink("/home/DraCorporation/public_html/Content/json/" . $nconst . '_resultat' . '.json');
                    }
                } else {
                    $resultat = "Content/img/NoPictureAvailable.png";
                }
            }
        } catch (Exception $e) {
            $resultat = "Content/img/NoPictureAvailable.png";
        }

        return $resultat;
    }


    /**
     * Retourne les titre que la personne a participé
     * @return [array] Contient les tconst que le nconst a participé
     */
    public function getTitleInformation($tconst)
    {
        $req = $this->bd->prepare('SELECT tconst, titleType, primaryTitle, originalTitle, isAdult, startYear, endYear, runtimeMinutes, genres FROM titlebasics where tconst= :tconst');
        $req->bindValue("tconst", $tconst);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPersonneInformation($nconst)
    {
        $req = $this->bd->prepare('SELECT  nconst, primaryname, birthyear, deathyear, primaryprofession, knownfortitles FROM namebasics where nconst= :nconst');
        $req->bindValue("nconst", $nconst);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rechercheTitreAvecArgu($arguRecherche)
    {
        $queryFilm = "SELECT tconst, primarytitle FROM titlebasics WHERE similarity(lower(unaccent(primarytitle)), lower(unaccent(:arg))) > 0.4";
        $resultFilms = $this->bd->prepare($queryFilm);
        $resultFilms->bindValue(':arg', $this->bd->quote($arguRecherche));
        $resultFilms->execute();
        return $resultFilms->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recherchePersonneAvecArgu($arguRecherche)
    {
        $queryPerso = "SELECT nconst, primaryname FROM namebasics WHERE similarity(lower(unaccent(primaryname)), lower(unaccent(:arg))) > 0.4 ";
        $resultPerso = $this->bd->prepare($queryPerso);
        $resultPerso->bindValue(':arg', $this->bd->quote($arguRecherche));
        $resultPerso->execute();
        return $resultPerso->fetchAll(PDO::FETCH_ASSOC);
    }

    public function verifyCredentials($email, $passw)
    {
        try {
            $query = "SELECT email, passw FROM users WHERE email = :email;";
            $stmt = $this->bd->prepare($query);
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetchAll()[0];
            if ($match = password_verify($passw, $row['passw'])) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public function createAccount($email, $passw, $username)
    {
        try {
            $query = "INSERT INTO Users VALUES (:email, :username, :passw);";
            $stmt = $this->bd->prepare($query);
            $passw = password_hash($passw, PASSWORD_DEFAULT);
            $stmt->execute(['email' => $email, 'username' => $username, 'passw' => $passw]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getName($email){
        try {
            $query = "SELECT name FROM users WHERE email = :email;";
            $stmt = $this->bd->prepare($query);
            $stmt->execute([':email' => $email]);
            $name = $stmt->fetchAll()[0]['name'];
            return $name;
        } catch (PDOException $e) {
            return "Name";
        }
    }
}
