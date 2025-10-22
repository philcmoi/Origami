<?php
class Commande {
    private $conn;
    private $table_name = "commandes";

    public $id;
    public $client_nom;
    public $client_email;
    public $produit;
    public $quantite;
    public $prix_unitaire;
    public $statut;
    public $date_commande;
    public $date_modification;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer une commande
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET client_nom=:client_nom, client_email=:client_email, 
                    produit=:produit, quantite=:quantite, prix_unitaire=:prix_unitaire, 
                    statut=:statut";

        $stmt = $this->conn->prepare($query);

        // Nettoyage des données
        $this->client_nom = htmlspecialchars(strip_tags($this->client_nom));
        $this->client_email = htmlspecialchars(strip_tags($this->client_email));
        $this->produit = htmlspecialchars(strip_tags($this->produit));

        // Liaison des paramètres
        $stmt->bindParam(":client_nom", $this->client_nom);
        $stmt->bindParam(":client_email", $this->client_email);
        $stmt->bindParam(":produit", $this->produit);
        $stmt->bindParam(":quantite", $this->quantite);
        $stmt->bindParam(":prix_unitaire", $this->prix_unitaire);
        $stmt->bindParam(":statut", $this->statut);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Lire toutes les commandes
    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY date_commande DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Lire une commande par ID
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->client_nom = $row['client_nom'];
            $this->client_email = $row['client_email'];
            $this->produit = $row['produit'];
            $this->quantite = $row['quantite'];
            $this->prix_unitaire = $row['prix_unitaire'];
            $this->statut = $row['statut'];
            $this->date_commande = $row['date_commande'];
            return true;
        }
        return false;
    }

    // Mettre à jour une commande
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET client_nom=:client_nom, client_email=:client_email, 
                    produit=:produit, quantite=:quantite, prix_unitaire=:prix_unitaire, 
                    statut=:statut
                WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Nettoyage des données
        $this->client_nom = htmlspecialchars(strip_tags($this->client_nom));
        $this->client_email = htmlspecialchars(strip_tags($this->client_email));
        $this->produit = htmlspecialchars(strip_tags($this->produit));

        // Liaison des paramètres
        $stmt->bindParam(":client_nom", $this->client_nom);
        $stmt->bindParam(":client_email", $this->client_email);
        $stmt->bindParam(":produit", $this->produit);
        $stmt->bindParam(":quantite", $this->quantite);
        $stmt->bindParam(":prix_unitaire", $this->prix_unitaire);
        $stmt->bindParam(":statut", $this->statut);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Supprimer une commande
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Calculer le total d'une commande
    public function getTotal() {
        return $this->quantite * $this->prix_unitaire;
    }

    // Rechercher des commandes
    public function search($keywords) {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE client_nom LIKE ? OR client_email LIKE ? OR produit LIKE ?
                ORDER BY date_commande DESC";

        $stmt = $this->conn->prepare($query);

        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";

        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        $stmt->bindParam(3, $keywords);

        $stmt->execute();
        return $stmt;
    }
}
?>