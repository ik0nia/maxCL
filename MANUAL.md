# Manual aplicatie maxCL / HPL Manager

## Scop
Aplicatia gestioneaza proiecte de productie pentru mobilier/atelier, stocul de placi HPL (cu culori, texturi, placi si resturi), accesoriile din magazie, livrarile, fisierele tehnice si orele estimate. Se lucreaza pe proiecte, unde se adauga produse, se rezerva materiale (HPL si accesorii) si se urmareste progresul pana la livrare.

## Fluxuri principale (pe scurt)
1. Setup + login: rulezi schema, intri cu admin.
2. Catalog HPL: definesti tipurile de culoare si texturile.
3. Stoc HPL: definesti placile HPL si adaugi piese (FULL/OFFCUT).
4. Clienti: creezi clienti si adrese de livrare.
5. Proiecte: creezi proiecte, adaugi produse, setezi statusuri.
6. Consum: rezervi HPL si accesorii, apoi marchezi ca "consumat" cand se debiteaza.
7. Livrari: inregistrezi livrarile pe produse.
8. Ore + fisiere: notezi orele estimate si incarci fisierele tehnice.

## Roluri si acces (pe rute)
Definit in `public/index.php` si `app/Core/Auth.php`:
- ADMIN: acces complet (utilizatori, audit, setari admin, costuri, db update, toate modulele).
- GESTIONAR: acces la proiecte, stoc HPL, magazie, consumuri materiale; nu are acces la utilizatori/audit/setari admin.
- OPERATOR: acces la proiecte si stoc; poate muta piese de stoc, dar nu poate crea/edita placi.
- VIZUALIZARE: doar citire (unde este permis).

## Navigatie principala (meniu)
Din `app/Views/layout/app.php`:
- Panou (dashboard)
- Proiecte
- Produse
- Clienti
- Placi HPL: Catalog, Tip culoare, Stoc, Bucati rest, Adaugare placi mici (nestocabile)
- Magazie: Stoc, Receptie
- Sistem: Costuri, Consumuri materiale, Admin Settings, DB update
- Utilizatori (ADMIN)
- Audit (ADMIN)
- Logout

## Entitati si legaturi (schema DB)
Extras din `database/schema.sql`:
- `finishes` (Tip culoare) -> folosit in `hpl_boards.face_color_id` si `hpl_boards.back_color_id`.
- `textures` -> folosit in `hpl_boards.face_texture_id` si `hpl_boards.back_texture_id`.
- `hpl_boards` -> are multe `hpl_stock_pieces`, consumuri HPL in proiecte.
- `hpl_stock_pieces` -> piese HPL in stoc (FULL/OFFCUT), legate optional la proiect (`project_id`).
- `clients` -> optional in `projects.client_id`.
- `client_groups` -> optional in `clients.client_group_id` si `projects.client_group_id`.
- `client_addresses` -> adrese multiple per client.
- `projects` -> proiecte de productie, legate la client sau grup.
- `products` -> produse de baza (folosite in proiecte).
- `project_products` -> legatura proiect-produs + cantitati + status productie.
- `labels` + `entity_labels` -> etichete generice (proiecte si produse), cu sursa DIRECT/INHERITED.
- `project_magazie_consumptions` -> consumuri de accesorii (legate la proiect si optional produs).
- `project_hpl_consumptions` -> consum HPL pe proiect (rezervari).
- `project_hpl_allocations` -> aloca rezervari HPL la produse.
- `project_product_hpl_consumptions` -> consum HPL pe produs (rezervat/consumat).
- `project_deliveries` + `project_delivery_items` -> livrari pe produse.
- `entity_files` -> fisiere atasate la proiect sau produs.
- `project_work_logs` -> ore CNC/Atelier (estimari).
- `entity_comments` -> discutii pe proiect.
- `magazie_items` + `magazie_movements` -> stoc accesorii + miscari.
- `audit_log` -> log complet de actiuni.
- `app_settings` -> setari firma (logo, date de contact).

## Formulare (inventar complet)
Nota: toate formularele POST includ `_csrf`.

### Autentificare si sesiune
- `/login` (POST): autentificare (`email`, `password`). Butonul este in `app/Views/auth/login.php`.
- `/logout` (POST): iesire (doar `_csrf`). Butonul este in layout (`app/Views/layout/app.php`).

### Setup
- `/setup/run` (POST): ruleaza schema + creeaza admin initial. Formular in `app/Views/setup/index.php`.

### Utilizatori (ADMIN)
- `/users/create` (POST): `email`, `name`, `role`, `is_active`, `password`, `password_confirm`.
- `/users/{id}/edit` (POST): aceleasi campuri; parola optionala; afiseaza ultimul login.
Formular in `app/Views/users/form.php`.

### Clienti
Formular principal (`app/Views/clients/form.php`):
- `/clients/create` (POST) sau `/clients/{id}/edit` (POST)
  - `type` (PERSOANA_FIZICA/FIRMA)
  - `name`
  - `client_group_id` (select existing)
  - `client_group_new` (creare grup din camp)
  - `cui` (obligatoriu doar pentru FIRMA)
  - `contact_person`, `phone`, `email`
  - `address`, `notes`

Formulare in pagina client (`app/Views/clients/show.php`):
- `/clients/{id}/delete` (POST): stergere client.
- `/clients/{id}/addresses/create` (POST): adauga adresa (`label`, `address`, `notes`, `is_default`).
- `/clients/{id}/addresses/{addrId}/edit` (POST): editare adresa (modal) cu aceleasi campuri.
- `/clients/{id}/addresses/{addrId}/delete` (POST): stergere adresa.

### Proiecte
Lista proiecte (`app/Views/projects/index.php`):
- `/projects` (GET): filtrare `q`, `status`.

Creare/editarare proiect (`app/Views/projects/form.php`):
- `/projects/create` (POST)
- `/projects/{id}/edit` (POST)
  - `name`, `status`, `priority`, `category`, `due_date`, `description`
  - `client_id` sau `client_group_id`
  - `labels` (CSV), `notes`, `technical_notes`

Pagina proiect (`app/Views/projects/show.php`) cu tab-uri:

Tab General:
- `/projects/{id}/edit` (POST): actualizare date proiect (aceleasi campuri ca mai sus).
- `/projects/{id}/delete` (POST): stergere proiect (ADMIN).
- `/projects/{id}/status` (POST): schimbare status (`status`, `note`).
- `/projects/{id}/labels/add` (POST): adauga eticheta (`label_name`).
- `/projects/{id}/labels/{labelId}/remove` (POST): sterge eticheta.

Tab Produse:
- `/projects/{id}/products/create` (POST): produs nou in proiect (`name`, `description`, `code`, `qty`, `sale_price`).
- `/projects/{id}/products/{ppId}/status` (POST): trecere la urmatorul status productie (camp ascuns `aviz_number` pentru AVIZAT).
- `/projects/{id}/products/{ppId}/hpl/create` (POST): consum HPL pe produs
  - `source` (PROJECT/REST), `piece_id`, `consume_mode` (FULL/HALF).
- `/projects/{id}/products/{ppId}/hpl/{cid}/cut` (POST): marcheaza HPL ca "debitat/consumat".
- `/projects/{id}/products/{ppId}/hpl/{cid}/unallocate` (POST): renunta la alocare HPL.
- `/projects/{id}/products/{ppId}/magazie/create` (POST): accesorii rezervate (`item_id`, `qty`).
- `/projects/{id}/products/{ppId}/magazie/consume` (POST): consuma accesorii rezervate.
- `/projects/{id}/products/{ppId}/magazie/{itemId}/unallocate` (POST): renunta la accesoriu rezervat (`src`, `qty`).
- `/projects/{id}/products/{ppId}/billing/update` (POST): facturare/livrare pe produs (`invoice_client_id`, `delivery_address_id`).
- `/projects/{id}/products/{ppId}/update` (POST): editare produs (`name`, `description`, `code`, `qty`, `sale_price`, `surface_mode`, `surface_m2`).
- `/projects/{id}/products/{ppId}/unlink` (POST): scoate produs din proiect.

Tab Consum:
- Consum accesorii (proiect):
  - `/projects/{id}/consum/magazie/create` (POST): `item_id`, `qty`, `project_product_id` (optional), `note`, `mode=RESERVED`.
  - `/projects/{id}/consum/magazie/{cid}/update` (POST): `qty`, `unit`, `mode`, `project_product_id`, `note`.
  - `/projects/{id}/consum/magazie/{cid}/delete` (POST): stergere.
- Consum HPL (proiect):
  - `/projects/{id}/consum/hpl/create` (POST): `board_id`, `offcut_dim` (optional), `qty_boards`, `note`, `mode=RESERVED`.
- Return piese HPL in stoc:
  - `/projects/{id}/hpl/pieces/{pieceId}/return` (POST): resturi REST (nestocate) inapoi in stoc (camp `note_user`).
  - `/stock/boards/{id}/pieces/move` (POST): return FULL/OFFCUT rezervat (campuri `from_piece_id`, `to_location`, `to_status`, `qty`, `note_user`).

Tab Livrari:
- `/projects/{id}/deliveries/create` (POST): `delivery_date`, `note`, `delivery_qty[ppId]` pentru fiecare produs.

Tab Fisiere:
- `/projects/{id}/files/upload` (POST): `entity_type` (projects/project_products), `entity_id` (daca e produs), `category`, `file`.
- `/projects/{id}/files/{fileId}/delete` (POST): stergere fisier.

Tab Ore:
- `/projects/{id}/hours/create` (POST): `work_type` (CNC/ATELIER), `project_product_id` (optional), `hours_estimated`, `note`.
- `/projects/{id}/hours/{workId}/delete` (POST): stergere inregistrare.

Tab Discutii:
- `/projects/{id}/discutii/create` (POST): `comment`.

### Produse
Lista produse (`app/Views/products/index.php`):
- `/products` (GET): filtrare `q` (cod/nume) si `label` (eticheta).

### Stoc HPL
Placi HPL (formular): `app/Views/stock/board_form.php`
- `/stock/boards/create` (POST) sau `/stock/boards/{id}/edit` (POST)
  - `code`, `name`, `brand`, `thickness_mm`
  - `std_height_mm`, `std_width_mm`
  - `sale_price`
  - `face_color_id`, `face_texture_id`
  - `back_color_id` (optional), `back_texture_id` (optional)
  - `notes`

Detalii placa (`app/Views/stock/board_details.php`):
- `/stock/boards/{id}/delete` (POST): stergere placa.
- `/stock/boards/{boardId}/pieces/{pieceId}/delete` (POST): stergere piesa.
- `/stock/boards/{id}/pieces/move` (POST): muta piese (`from_piece_id`, `qty`, `to_status`, `to_location`, `note`).
- `/stock/boards/{id}/pieces/add` (POST): adauga piesa (`piece_type`, `height_mm`, `width_mm`, `qty`, `location`, `notes`).

Piese interne (nestocabile) (`app/Views/hpl/internal_pieces/index.php`):
- `/hpl/piese-interne/create` (POST): `board_id`, `height_mm`, `width_mm`, `qty`, `location`, `notes`.

### Tip culoare si texturi HPL
Tip culoare (`app/Views/catalog/finishes/form.php`):
- `/hpl/tip-culoare/create` (POST) si `/hpl/tip-culoare/{id}/edit` (POST)
  - `code`, `color_name`, `color_code`
  - `image` (obligatoriu la creare)
- `/hpl/tip-culoare/{id}/delete` (POST): stergere tip culoare.

Texturi inline (in `app/Views/catalog/finishes/index.php`):
- `/hpl/tip-culoare/texturi/create` (POST): `code`, `name`.
- `/hpl/tip-culoare/texturi/{id}/edit` (POST): `code`, `name` (modal).
- `/hpl/tip-culoare/texturi/{id}/delete` (POST): stergere.

Legacy (forme existente, dar rutele sunt redirectionate):
- `/hpl/texturi` (GET) -> redirect catre `/hpl/tip-culoare#texturi`.
- Formularele din `app/Views/hpl/textures/*` raman in cod, dar rutele POST nu sunt definite in `public/index.php`.

### Materiale si variante (legacy)
Forme existente in `app/Views/catalog/materials/*` si `app/Views/catalog/variants/*`, dar listele sunt redirectionate la `/stock`:
- Materiale (`/catalog/materials/create` / `/catalog/materials/{id}/edit`):
  - `code`, `name`, `brand`, `thickness_mm`, `notes`, `track_stock`.
- Variante (`/catalog/variants/create` / `/catalog/variants/{id}/edit`):
  - `material_id`, `finish_face_id`, `finish_back_id` (optional).

### Magazie (accesorii)
Receptie (`app/Views/magazie/receptie/index.php`):
- `/magazie/receptie/create` (POST)
  - `winmentor_code[]`, `name[]`, `qty[]`, `unit_price[]` (multi-linii)
  - `note` (optional)

Stoc (`app/Views/magazie/stoc/index.php`):
- `/magazie/stoc` (GET): filtrare `q`.
- `/magazie/stoc/{id}/consume` (POST): scade stoc (`qty`, `project_code` optional).
- `/magazie/stoc/{id}/delete` (POST): stergere item (ADMIN).

### Sistem
Costuri (`app/Views/system/cost_settings.php`):
- `/system/costuri` (POST): `cost_labor_per_hour`, `cost_cnc_per_hour`.

Consumuri materiale (`app/Views/system/material_consumptions.php`):
- `/system/consumuri-materiale` (GET): filtre `tab`, `date_from`, `date_to`, `mode`.

Setari admin (`app/Views/system/admin_settings.php`):
- `/system/admin-settings/company/update` (POST): date firma + logo.
- `/system/admin-settings/snapshot/create` (POST): creare snapshot.
- `/system/admin-settings/snapshot/restore` (POST): restaurare snapshot (`snapshot`).

DB update (`app/Views/system/db_update.php`):
- `/system/db-update/run` (POST): ruleaza migrari DB.

### Audit
Jurnal (`app/Views/audit/index.php`):
- `/audit` (GET): filtre `user_id`, `action`, `date_from`, `date_to`.

## Legaturi intre module (practic)
- Placa HPL foloseste Tip Culoare + Textura (fata/verso).
- Stocul HPL are piese FULL/OFFCUT si statusuri (AVAILABLE/RESERVED/CONSUMED/SCRAP).
- Proiectul poate fi legat de client sau grup.
- Produsele sunt asociate proiectelor si primesc etichete din proiect.
- Consum HPL:
  - Proiect: rezervi placi/resturi (qty si mp).
  - Produs: aloci din rezervari si marchezi "Debitat" cand consumi efectiv.
- Consum accesorii (Magazie):
  - Rezervi pe proiect/produs si consumi la final (status productie).
- Livrarile au cantitati pe produs (istoric).
- Fisierele sunt atasate la proiect sau produs.
- Orele sunt atasate la proiect sau produs (estimari).

## API folosite de formulare (select2/autocomplete)
Definite in `public/index.php`:
- `/api/finishes/search` -> autocomplete pentru Tip Culoare (formular placa).
- `/api/hpl/boards/search` -> selectare placa HPL (consum proiect).
- `/api/hpl/boards/offcuts` -> lista resturi disponibile pentru placa selectata.
- `/api/hpl/pieces/search` -> selectare piesa HPL rezervata (consum pe produs).
- `/api/magazie/items/search` -> cautare accesorii in Magazie.

## Observatii
- Formularele de tip "sterge" sunt de obicei in liste (butoane mici cu confirmare).
- Multe actiuni sunt conditionate de rol (ex: stergere, creare placi, consumuri).
- Pentru piese interne HPL se seteaza `is_accounting=0`, deci nu intra in totaluri.
- Produsele nu se gestioneaza separat; se creeaza direct in proiect.
