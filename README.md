# CsvImporter

Import et simulation d'import d'un catalogue produit depuis un fichier CSV, en ligne de commande.

```
php Thelia csvimporter:import-catalog <fichier.csv> --save-mapping="Fournisseur X" --dry-run
php Thelia csvimporter:import-catalog <fichier.csv> --mapping="Fournisseur X"
```

## Options

- `--save-mapping=<nom>` : devine l'association colonne → champ d'après l'en-tête du fichier, l'affiche et la garde sous un nouveau profil.
- `--mapping=<id ou nom>` : réutilise un profil existant. L'un des deux est obligatoire.
- `--mode=create_or_update` (défaut), `update_only` ou `create_only`.
- `--dry-run` : simule. Même code que l'import réel, sans aucune écriture dans le catalogue.

## Code de sortie

- `0` : terminé, toutes les lignes traitées.
- `1` : l'import n'a pas tourné (fichier illisible, profil inadapté, autre import en cours).
- `2` : arguments refusés.
- `3` : terminé, mais des lignes ont été refusées (leur motif est affiché et stocké dans le rapport du job).

## Fichier

- Le séparateur et l'encodage sont détectés ; un profil peut les imposer.
- La colonne de référence est obligatoire : elle identifie le produit à créer ou à mettre à jour.
- Une colonne `C:<nom>` est une caractéristique. Les colonnes `D:<nom>` (déclinaisons) ne sont pas encore importées.
- Une ligne fausse n'arrête pas l'import : elle est refusée avec son motif, les autres s'écrivent, chacune dans sa propre transaction.
- Une cellule vide n'efface jamais le champ d'un produit existant.

Les écrans du back-office, les images et l'export rechargeable ne sont pas livrés dans cette version.
