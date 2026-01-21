<?php
use App\Core\Url;
use App\Core\View;

ob_start();
?>
<div class="app-page-title">
  <div>
    <h1 class="m-0">Manual</h1>
    <div class="text-muted">Ghid practic de utilizare + documentatie avansata</div>
  </div>
</div>

<ul class="nav nav-tabs mb-3" id="manualTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="manual-basic-tab" data-bs-toggle="tab" data-bs-target="#manual-basic" type="button" role="tab" aria-controls="manual-basic" aria-selected="true">
      Manual
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="manual-advanced-tab" data-bs-toggle="tab" data-bs-target="#manual-advanced" type="button" role="tab" aria-controls="manual-advanced" aria-selected="false">
      Manual avansat
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="manual-basic" role="tabpanel" aria-labelledby="manual-basic-tab">
    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Bine ai venit</h2>
      <p class="mb-2">
        Acest ghid este versiunea prietenoasa pentru utilizatorii aplicatiei. Iti explica pe intelesul tuturor
        ce face fiecare modul si cum se leaga intre ele. Daca ai nevoie de detalii tehnice (rute, tabele, validari),
        foloseste tab-ul <strong>Manual avansat</strong>.
      </p>
      <ul class="mb-0">
        <li>Aplicatia urmareste fluxul complet: <strong>Client → Oferta → Proiect → Consum → Livrare</strong>.</li>
        <li>Stocul HPL si magazia sunt actualizate la fiecare consum/rezervare.</li>
        <li>Daca nu vezi un buton sau un meniu, rolul tau nu permite acces.</li>
      </ul>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Start rapid (5 pasi)</h2>
      <ol class="mb-0">
        <li><strong>Autentificare</strong> in aplicatie.</li>
        <li><strong>Verifica Panoul</strong> (Dashboard) pentru stocuri si culori dominante.</li>
        <li><strong>Configureaza HPL</strong>: Tip culoare, texturi si placi in Stoc.</li>
        <li><strong>Creaza Client + Oferta</strong> si adauga produse, HPL si accesorii.</li>
        <li><strong>Converteste Oferta in Proiect</strong> si consuma materiale pe masura lucrarilor.</li>
      </ol>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Modulele, pe intelesul tuturor</h2>
      <ul class="mb-0">
        <li><strong>Panou</strong> – vedere rapida asupra stocului HPL si culorilor dominante.</li>
        <li><strong>HPL / Tip culoare</strong> – definesti culori si texturi folosite la placi.</li>
        <li><strong>Stoc HPL</strong> – gestionezi placi si piese (full/offcut).</li>
        <li><strong>Bucati rest / Piese interne</strong> – evidenta resturilor si a placilor mici nestocabile.</li>
        <li><strong>Magazie</strong> – accesorii: receptie marfa, stoc, consum.</li>
        <li><strong>Clienti</strong> – lista clienti si adresele lor.</li>
        <li><strong>Oferte</strong> – configurare produse, HPL, accesorii si manopera.</li>
        <li><strong>Proiecte</strong> – executia comenzii, consumuri si livrari.</li>
        <li><strong>Utilizatori</strong> – administrare conturi (doar ADMIN).</li>
        <li><strong>Sistem & Audit</strong> – setari si jurnal de activitate.</li>
      </ul>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Operatiuni uzuale (ce faci in formulare)</h2>
      <ul class="mb-0">
        <li><strong>Clienti</strong>: adaugi client nou, editezi date, gestionezi adresele si poti sterge (doar ADMIN).</li>
        <li><strong>Oferte</strong>: creezi oferta, actualizezi datele, adaugi produse, HPL, accesorii si manopera.</li>
        <li><strong>Proiecte</strong>: creezi proiect sau convertesti din oferta, actualizezi status, adaugi produse.</li>
        <li><strong>Consumuri</strong>: aloci HPL si accesorii, apoi consumi in functie de debitare/lucru.</li>
        <li><strong>Fisiere</strong>: incarci documente pe proiect si le poti sterge ulterior.</li>
        <li><strong>Livrari</strong>: adaugi livrari si urmaresti ce s-a predat clientului.</li>
        <li><strong>Utilizatori</strong>: ADMIN poate crea si edita conturi.</li>
      </ul>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Povestea unei comenzi (Oferta → Proiect)</h2>
      <p class="mb-2">
        In practica, lucrurile merg asa: un client are o cerere, creezi o <strong>Oferta</strong>,
        adaugi produsele necesare si materialele aferente (HPL, accesorii, manopera).
        Cand oferta este acceptata, o <strong>convertesti in Proiect</strong>.
      </p>
      <p class="mb-0">
        In proiect, fiecare produs poate avea consumuri de HPL si magazie. Pe masura ce debitezi,
        stocul se scade automat, iar resturile pot fi intoarse in stoc ca bucati disponibile.
      </p>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Stoc HPL pe scurt</h2>
      <ul class="mb-0">
        <li><strong>Placi</strong> – sunt definite cu dimensiuni, brand si finisaje.</li>
        <li><strong>Piese</strong> – pot fi <em>FULL</em> sau <em>OFFCUT</em>, cu status: disponibil/rezervat/consumat.</li>
        <li><strong>Rezervare</strong> – o piesa se poate aloca unui proiect.</li>
        <li><strong>Consum</strong> – piesele trec in status consumat la debitare.</li>
        <li><strong>Retur</strong> – resturile pot fi reintroduse in stoc.</li>
      </ul>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Logica stocului (explicata simplu)</h2>
      <ul class="mb-0">
        <li><strong>Placa</strong> este baza: are dimensiuni standard si finisaje fata/verso.</li>
        <li><strong>Piesa</strong> este unitatea de lucru: dimensiune, cantitate si locatie.</li>
        <li><strong>Status</strong>: Disponibil → Rezervat → Consumat (sau deseu).</li>
        <li><strong>Tip piese</strong>: FULL (placa intreaga) sau OFFCUT (rest).</li>
        <li><strong>Piese interne</strong> sunt nestocabile (nu intra in totaluri de stoc).</li>
      </ul>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Magazie (accesorii) pe scurt</h2>
      <ul class="mb-0">
        <li>In <strong>Receptie marfa</strong> adaugi intrari de produse in magazie.</li>
        <li>In <strong>Stoc Magazie</strong> vezi disponibilul si poti consuma pe proiect.</li>
        <li>Consumurile sunt urmarite si la nivel de produs din proiect.</li>
      </ul>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Formulare: reguli generale</h2>
      <ul class="mb-0">
        <li>Campurile obligatorii trebuie completate (validare pe server).</li>
        <li>Valorile numerice (cantitati, dimensiuni) trebuie sa fie valide.</li>
        <li>Erorile si confirmarile apar ca mesaje tip toast.</li>
        <li>Actiunile POST sunt protejate cu token CSRF.</li>
      </ul>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Lucrul in proiect (util, zi de zi)</h2>
      <ul class="mb-0">
        <li><strong>Produse</strong> – adaugi produse in proiect si le actualizezi statusul.</li>
        <li><strong>Consumuri</strong> – aloci HPL si accesorii, apoi consumi pe masura debitarii.</li>
        <li><strong>Livrari</strong> – creezi livrari si urmaresti ce s-a predat clientului.</li>
        <li><strong>Fisiere</strong> – incarci documente tehnice sau poze la proiect.</li>
        <li><strong>Ore lucrate</strong> – notezi timpul pe proiect sau produs.</li>
        <li><strong>Discutii & etichete</strong> – pastrezi contextul si organizarea.</li>
      </ul>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Roluri si acces</h2>
      <ul class="mb-0">
        <li><strong>ADMIN</strong> – acces complet, utilizatori si setari sistem.</li>
        <li><strong>GESTIONAR</strong> – operatiuni complete pe stoc si proiecte.</li>
        <li><strong>OPERATOR</strong> – acces operational (citire + actiuni permise in stoc/proiect).</li>
        <li><strong>VIZUALIZARE</strong> – acces doar la citire unde este permis.</li>
      </ul>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 class="h5">Cand ai nevoie de detalii tehnice</h2>
      <p class="mb-0">
        Pentru rute, tabele, validari si explicatii complete ale formularului, mergi la tab-ul
        <strong>Manual avansat</strong>.
      </p>
    </div>
  </div>

  <div class="tab-pane fade" id="manual-advanced" role="tabpanel" aria-labelledby="manual-advanced-tab">
    <div class="app-page-title">
      <div>
        <h1 class="m-0">Manual avansat pentru utilizatori</h1>
        <div class="text-muted">Documentatie operationala bazata strict pe codul aplicatiei</div>
      </div>
    </div>

    <div class="card app-card p-3 mb-3">
      <h2 id="scop" class="h5">Scop si principii</h2>
      <ul class="mb-0">
        <li>Acest manual descrie functionalitatile existente in cod (rute, formulare, validari, procese).</li>
        <li>Nu sunt introduse functionalitati sau campuri inexistente.</li>
        <li>Toate actiunile POST sunt protejate cu token CSRF (<code>_csrf</code>).</li>
        <li>Rolurile sunt aplicate la nivel de ruta prin <code>Auth::requireRole()</code>.</li>
      </ul>
    </div>

<div class="card app-card p-3 mb-3">
  <h2 id="cuprins" class="h5">Cuprins</h2>
  <ul class="mb-0">
    <li><a href="#instalare">1. Instalare si setup</a></li>
    <li><a href="#autentificare">2. Autentificare si roluri</a></li>
    <li><a href="#securitate">3. Router, sesiune, CSRF, audit</a></li>
    <li><a href="#dashboard">4. Panou (Dashboard)</a></li>
    <li><a href="#hpl">5. HPL: catalog, culori, texturi, stoc</a></li>
    <li><a href="#magazie">6. Magazie</a></li>
    <li><a href="#clienti">7. Clienti</a></li>
    <li><a href="#oferte">8. Oferte</a></li>
    <li><a href="#proiecte">9. Proiecte</a></li>
    <li><a href="#produse">10. Produse</a></li>
    <li><a href="#utilizatori">11. Utilizatori</a></li>
    <li><a href="#sistem">12. Sistem</a></li>
    <li><a href="#audit">13. Audit</a></li>
    <li><a href="#api">14. API intern</a></li>
    <li><a href="#uploads">15. Upload-uri si fisiere</a></li>
    <li><a href="#db">16. Entitati si relatii DB</a></li>
    <li><a href="#fluxuri">17. Fluxuri complexe (Mermaid)</a></li>
    <li><a href="#legacy">18. Rute legacy / compat</a></li>
  </ul>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="instalare" class="h5">1. Instalare si setup</h2>
  <p class="mb-2">Instalarea se face prin rutele publice <code>/setup</code> si <code>/setup/run</code>.</p>
  <ul>
    <li>Fisier schema: <code>database/schema.sql</code> (executat la setup).</li>
    <li>Seed admin: <code>admin@local / admin123</code>, rol <code>ADMIN</code> (creat idempotent).</li>
    <li>Auto-migrari best-effort: <code>DbMigrations::runAuto()</code> ruleaza la fiecare request.</li>
    <li>Configurare DB: prin <code>.env</code> (DB_HOST/DB_NAME/DB_USER/DB_PASS).</li>
  </ul>

  <h3 class="h6 mt-3">Formular: Ruleaza setup</h3>
  <p class="text-muted">Ruta: <code>POST /setup/run</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr>
        <th>Eticheta UI</th>
        <th>name</th>
        <th>Tip</th>
        <th>Validari</th>
        <th>DB / Structura</th>
        <th>Observatii</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Token CSRF</td>
        <td>_csrf</td>
        <td>hidden</td>
        <td>Obligatoriu (Csrf::verify)</td>
        <td>n/a</td>
        <td>Protejeaza formularul.</td>
      </tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="autentificare" class="h5">2. Autentificare si roluri</h2>
  <p class="mb-2">Autentificarea foloseste tabela <code>users</code> si sesiune PHP.</p>
  <ul>
    <li>Login: <code>GET /login</code> (formular), <code>POST /login</code> (Auth::attempt).</li>
    <li>Logout: <code>POST /logout</code>.</li>
    <li>Cont activ: <code>users.is_active = 1</code> este necesar.</li>
  </ul>

  <h3 class="h6 mt-3">Formular: Login</h3>
  <p class="text-muted">Ruta: <code>POST /login</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr>
        <th>Eticheta UI</th>
        <th>name</th>
        <th>Tip</th>
        <th>Validari</th>
        <th>DB / Structura</th>
        <th>Observatii</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Email</td>
        <td>email</td>
        <td>email</td>
        <td>Obligatoriu (server)</td>
        <td>users.email</td>
        <td>Folosit la cautarea utilizatorului.</td>
      </tr>
      <tr>
        <td>Parola</td>
        <td>password</td>
        <td>password</td>
        <td>Obligatoriu (server)</td>
        <td>users.password_hash</td>
        <td>Se verifica cu <code>password_verify</code>.</td>
      </tr>
      <tr>
        <td>Token CSRF</td>
        <td>_csrf</td>
        <td>hidden</td>
        <td>Obligatoriu (Csrf::verify)</td>
        <td>n/a</td>
        <td>Protejeaza formularul.</td>
      </tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Logout</h3>
  <p class="text-muted">Ruta: <code>POST /logout</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr>
        <th>Eticheta UI</th>
        <th>name</th>
        <th>Tip</th>
        <th>Validari</th>
        <th>DB / Structura</th>
        <th>Observatii</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Token CSRF</td>
        <td>_csrf</td>
        <td>hidden</td>
        <td>Obligatoriu (Csrf::verify)</td>
        <td>n/a</td>
        <td>Folosit in header.</td>
      </tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Roluri (Auth::ROLE_*)</h3>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr>
        <th>Rol</th>
        <th>Cod</th>
        <th>Observatii acces</th>
      </tr>
    </thead>
    <tbody>
      <tr><td>Administrator</td><td>ADMIN</td><td>Acces complet. In <code>requireRole</code>, ADMIN permite si MANAGER.</td></tr>
      <tr><td>Manager</td><td>MANAGER</td><td>Tratata echivalent cu ADMIN (ADMIN ↔ MANAGER).</td></tr>
      <tr><td>Gestionar</td><td>GESTIONAR</td><td>Acces operational la stoc, magazie, proiecte/oferte.</td></tr>
      <tr><td>Operator</td><td>OPERATOR</td><td>Acces operational limitat (citire + actiuni definite in rute).</td></tr>
      <tr><td>Vizualizare</td><td>VIZUALIZARE</td><td>Rol existent in sistem, dar nefolosit in rute restrictive.</td></tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="securitate" class="h5">3. Router, sesiune, CSRF, audit</h2>
  <ul class="mb-0">
    <li>Router principal: <code>public/index.php</code>.</li>
    <li>Sesiune: <code>App\Core\Session</code> + <code>Auth::user()</code>.</li>
    <li>CSRF: <code>Csrf::token()</code> in form, <code>Csrf::verify()</code> la POST.</li>
    <li>Audit: <code>Audit::log()</code> scrie in tabela <code>audit_log</code>.</li>
  </ul>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="dashboard" class="h5">4. Panou (Dashboard)</h2>
  <ul>
    <li>Rute: <code>GET /</code> si <code>GET /api/dashboard/top-colors</code> (auth necesar).</li>
    <li>Scop: statistici agregate despre stocul HPL (culori dominante, grosimi).</li>
    <li>DB: agregari din <code>hpl_boards</code> si <code>hpl_stock_pieces</code> (via StockStats).</li>
  </ul>
  <p class="mb-0">Nu exista formular POST. Campul de cautare trimite request AJAX catre <code>/api/dashboard/top-colors?q=...</code>.</p>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="hpl" class="h5">5. HPL: catalog, tip culoare, texturi, stoc</h2>

  <h3 class="h6 mt-3">5.1 Catalog HPL</h3>
  <ul>
    <li>Ruta: <code>GET /hpl/catalog</code></li>
    <li>API: <code>GET /api/hpl/catalog</code> (parametri: <code>q</code>, <code>in_stock</code>).</li>
    <li>Scop: afiseaza stoc agregat pe Tip culoare si grosimi.</li>
  </ul>

  <h3 class="h6 mt-3" id="hpl-tip-culoare">5.2 Tip culoare (finishes)</h3>
  <ul>
    <li>Rute: <code>/hpl/tip-culoare</code>, <code>/hpl/tip-culoare/create</code>, <code>/hpl/tip-culoare/{id}/edit</code>, <code>/hpl/tip-culoare/{id}/delete</code>.</li>
    <li>DB: <code>finishes</code> (campurile textura sunt pastrate doar pentru compatibilitate).</li>
    <li>Audit: <code>COLOR_TYPE_CREATE</code>, <code>COLOR_TYPE_UPDATE</code>, <code>COLOR_TYPE_DELETE</code>.</li>
  </ul>

  <h4 class="h6 mt-3">Formular: Tip culoare (creare / editare)</h4>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr>
        <th>Eticheta UI</th>
        <th>name</th>
        <th>Tip</th>
        <th>Validari</th>
        <th>DB / Structura</th>
        <th>Observatii</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Cod *</td>
        <td>code</td>
        <td>text</td>
        <td>Obligatoriu</td>
        <td>finishes.code</td>
        <td>Unic in DB.</td>
      </tr>
      <tr>
        <td>Nume culoare *</td>
        <td>color_name</td>
        <td>text</td>
        <td>Obligatoriu</td>
        <td>finishes.color_name</td>
        <td>Afisat in catalog si stoc.</td>
      </tr>
      <tr>
        <td>Cod culoare</td>
        <td>color_code</td>
        <td>text</td>
        <td>Optional</td>
        <td>finishes.color_code</td>
        <td>Folosit la cautare si display.</td>
      </tr>
      <tr>
        <td>Imagine (obligatoriu)</td>
        <td>image</td>
        <td>file</td>
        <td>Obligatoriu la creare; JPG/PNG/WEBP</td>
        <td>finishes.thumb_path, finishes.image_path</td>
        <td>Salvat in <code>storage/uploads/finishes</code>, cu thumbnail 256px.</td>
      </tr>
      <tr>
        <td>Token CSRF</td>
        <td>_csrf</td>
        <td>hidden</td>
        <td>Obligatoriu</td>
        <td>n/a</td>
        <td>Protejeaza formularul.</td>
      </tr>
    </tbody>
  </table>

  <h4 class="h6 mt-3">Formular: Sterge tip culoare</h4>
  <p class="text-muted">Ruta: <code>POST /hpl/tip-culoare/{id}/delete</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr>
        <td>Token CSRF</td>
        <td>_csrf</td>
        <td>hidden</td>
        <td>Obligatoriu</td>
        <td>finishes (DELETE)</td>
        <td>Poate esua daca este folosit de placi.</td>
      </tr>
    </tbody>
  </table>

  <h3 class="h6 mt-4" id="hpl-texturi">5.3 Texturi</h3>
  <ul>
    <li>Rute: <code>/hpl/tip-culoare/texturi/create</code>, <code>/hpl/tip-culoare/texturi/{id}/edit</code>, <code>/hpl/tip-culoare/texturi/{id}/delete</code>.</li>
    <li>DB: <code>textures</code>.</li>
    <li>Audit: <code>TEXTURE_CREATE</code>, <code>TEXTURE_UPDATE</code>, <code>TEXTURE_DELETE</code>.</li>
  </ul>

  <h4 class="h6 mt-3">Formular: Textura (adauga)</h4>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cod</td><td>code</td><td>text</td><td>Optional</td><td>textures.code</td><td>Poate fi gol.</td></tr>
      <tr><td>Denumire *</td><td>name</td><td>text</td><td>Obligatoriu</td><td>textures.name</td><td>Validat cu Validator::required.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>Protejeaza formularul.</td></tr>
    </tbody>
  </table>

  <h4 class="h6 mt-3">Formular: Textura (editare)</h4>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cod</td><td>code</td><td>text</td><td>Optional</td><td>textures.code</td><td>Actualizeaza codul.</td></tr>
      <tr><td>Denumire *</td><td>name</td><td>text</td><td>Obligatoriu</td><td>textures.name</td><td>Validator::required.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>Protejeaza formularul.</td></tr>
    </tbody>
  </table>

  <h4 class="h6 mt-3">Formular: Sterge textura</h4>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>textures (DELETE)</td><td>Poate esua daca textura e folosita.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-4" id="hpl-stoc">5.4 Stoc HPL (placi + piese)</h3>
  <ul>
    <li>Rute: <code>/stock</code>, <code>/stock/boards/create</code>, <code>/stock/boards/{id}</code>, <code>/stock/boards/{id}/edit</code>.</li>
    <li>DB: <code>hpl_boards</code> (placi), <code>hpl_stock_pieces</code> (piese).</li>
    <li>Reguli: <code>piece_type</code> = FULL/OFFCUT, <code>status</code> = AVAILABLE/RESERVED/CONSUMED/SCRAP.</li>
    <li>Distinctie contabil: <code>hpl_stock_pieces.is_accounting</code> (1 = stoc contabil; 0 = piese interne).</li>
  </ul>

  <h4 class="h6 mt-3">Formular: Placa HPL (creare / editare)</h4>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cod *</td><td>code</td><td>text</td><td>Obligatoriu</td><td>hpl_boards.code</td><td>Unic.</td></tr>
      <tr><td>Denumire *</td><td>name</td><td>text</td><td>Obligatoriu</td><td>hpl_boards.name</td><td>—</td></tr>
      <tr><td>Brand *</td><td>brand</td><td>text</td><td>Obligatoriu</td><td>hpl_boards.brand</td><td>—</td></tr>
      <tr><td>Grosime (mm) *</td><td>thickness_mm</td><td>number</td><td>int >= 1</td><td>hpl_boards.thickness_mm</td><td>Validator::int.</td></tr>
      <tr><td>Lungime standard (mm) *</td><td>std_height_mm</td><td>number</td><td>int >= 1</td><td>hpl_boards.std_height_mm</td><td>Ordinea este L x l.</td></tr>
      <tr><td>Latime standard (mm) *</td><td>std_width_mm</td><td>number</td><td>int >= 1</td><td>hpl_boards.std_width_mm</td><td>—</td></tr>
      <tr><td>Pret vanzare (lei)</td><td>sale_price</td><td>text</td><td>decimal >= 0</td><td>hpl_boards.sale_price</td><td>Calcul mp automat (sale_price_per_m2).</td></tr>
      <tr><td>Culoare fata *</td><td>face_color_id</td><td>hidden</td><td>int >= 1</td><td>hpl_boards.face_color_id</td><td>Autocompletare /api/finishes/search.</td></tr>
      <tr><td>Textura fata *</td><td>face_texture_id</td><td>select</td><td>int >= 1</td><td>hpl_boards.face_texture_id</td><td>Select din texturi.</td></tr>
      <tr><td>Culoare verso (optional)</td><td>back_color_id</td><td>hidden</td><td>int >= 1 (optional)</td><td>hpl_boards.back_color_id</td><td>Gol = aceeasi fata/verso.</td></tr>
      <tr><td>Textura verso (optional)</td><td>back_texture_id</td><td>select</td><td>int >= 1 (optional)</td><td>hpl_boards.back_texture_id</td><td>Gol = aceeasi fata/verso.</td></tr>
      <tr><td>Note</td><td>notes</td><td>text</td><td>Optional</td><td>hpl_boards.notes</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h4 class="h6 mt-3">Formular: Adauga piesa in stoc</h4>
  <p class="text-muted">Ruta: <code>POST /stock/boards/{id}/pieces/add</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Tip</td><td>piece_type</td><td>select</td><td>FULL/OFFCUT</td><td>hpl_stock_pieces.piece_type</td><td>Daca dimensiunile difera de standard, se salveaza OFFCUT.</td></tr>
      <tr><td>Lungime (mm)</td><td>height_mm</td><td>number</td><td>int >= 1</td><td>hpl_stock_pieces.height_mm</td><td>Comparat cu dimensiunile standard.</td></tr>
      <tr><td>Latime (mm)</td><td>width_mm</td><td>number</td><td>int >= 1</td><td>hpl_stock_pieces.width_mm</td><td>—</td></tr>
      <tr><td>Buc</td><td>qty</td><td>number</td><td>int >= 1</td><td>hpl_stock_pieces.qty</td><td>Cumulare daca exista piesa identica.</td></tr>
      <tr><td>Locatie</td><td>location</td><td>select</td><td>Din lista predefinita</td><td>hpl_stock_pieces.location</td><td>Producție => status RESERVED.</td></tr>
      <tr><td>Note</td><td>notes</td><td>text</td><td>Optional</td><td>hpl_stock_pieces.notes</td><td>Se adauga la nota existenta la cumulare.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h4 class="h6 mt-3">Formular: Mutare piesa</h4>
  <p class="text-muted">Ruta: <code>POST /stock/boards/{id}/pieces/move</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Din piesa</td><td>from_piece_id</td><td>select</td><td>int >= 1</td><td>hpl_stock_pieces.id</td><td>Piesa sursa.</td></tr>
      <tr><td>Bucati de mutat</td><td>qty</td><td>number</td><td>int >= 1</td><td>hpl_stock_pieces.qty</td><td>Poate crea split.</td></tr>
      <tr><td>Status destinatie</td><td>to_status</td><td>select</td><td>AVAILABLE/RESERVED/CONSUMED/SCRAP</td><td>hpl_stock_pieces.status</td><td>Locatia Producție forteaza RESERVED.</td></tr>
      <tr><td>Locatie destinatie</td><td>to_location</td><td>select</td><td>Din lista predefinita</td><td>hpl_stock_pieces.location</td><td>—</td></tr>
      <tr><td>Notita</td><td>note</td><td>textarea</td><td>Obligatoriu</td><td>hpl_stock_pieces.notes</td><td>Combinata cu note_user (daca exista).</td></tr>
      <tr><td>Nota utilizator</td><td>note_user</td><td>hidden</td><td>Optional</td><td>hpl_stock_pieces.notes</td><td>Folosita la retururi din proiect.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h4 class="h6 mt-3">Formulare: Stergere placa / piesa</h4>
  <p class="text-muted">Rute: <code>/stock/boards/{id}/delete</code>, <code>/stock/boards/{boardId}/pieces/{pieceId}/delete</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>hpl_boards / hpl_stock_pieces</td><td>Sterge placa sau piesa.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-4" id="hpl-offcuts">5.5 Bucati rest</h3>
  <ul>
    <li>Ruta: <code>GET /hpl/bucati-rest</code> (filtre bucket/scrap).</li>
    <li>Afiseaza piese OFFCUT (contabile + interne).</li>
    <li>Audit: <code>HPL_STOCK_TRASH</code>, <code>FILE_UPLOAD</code>.</li>
  </ul>

  <h4 class="h6 mt-3">Formular: Incarca poza piesa</h4>
  <p class="text-muted">Ruta: <code>POST /hpl/bucati-rest/{pieceId}/photo</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Poză</td><td>photo</td><td>file</td><td>JPG/PNG/WEBP</td><td>entity_files</td><td>Inlocuieste pozele vechi (categoria internal_piece_photo).</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h4 class="h6 mt-3">Formular: Scoate piesa din stoc</h4>
  <p class="text-muted">Ruta: <code>POST /hpl/bucati-rest/{pieceId}/trash</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Nota explicativa *</td><td>note</td><td>text</td><td>Obligatoriu</td><td>hpl_stock_pieces.notes</td><td>Status -> SCRAP, locatie -> Depozit (Stricat).</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-4" id="hpl-interne">5.6 Piese interne (nestocabile)</h3>
  <ul>
    <li>Ruta: <code>GET /hpl/piese-interne</code>, <code>POST /hpl/piese-interne/create</code>.</li>
    <li>DB: <code>hpl_stock_pieces</code> cu <code>is_accounting=0</code>.</li>
    <li>Audit: <code>INTERNAL_PIECE_CREATE</code>, <code>FILE_UPLOAD</code>.</li>
  </ul>

  <h4 class="h6 mt-3">Formular: Adauga piesa interna</h4>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Tip placa</td><td>board_id</td><td>hidden</td><td>int >= 1</td><td>hpl_stock_pieces.board_id</td><td>Se selecteaza din lista de placi.</td></tr>
      <tr><td>Lungime (mm)</td><td>height_mm</td><td>number</td><td>int >= 1</td><td>hpl_stock_pieces.height_mm</td><td>Se salveaza ca OFFCUT.</td></tr>
      <tr><td>Latime (mm)</td><td>width_mm</td><td>number</td><td>int >= 1</td><td>hpl_stock_pieces.width_mm</td><td>—</td></tr>
      <tr><td>Buc</td><td>qty</td><td>number</td><td>int >= 1</td><td>hpl_stock_pieces.qty</td><td>Cumulare daca exista piesa identica.</td></tr>
      <tr><td>Locatie</td><td>location</td><td>select</td><td>Din lista predefinita</td><td>hpl_stock_pieces.location</td><td>Producție => status RESERVED.</td></tr>
      <tr><td>Note</td><td>notes</td><td>text</td><td>Optional</td><td>hpl_stock_pieces.notes</td><td>—</td></tr>
      <tr><td>Poză (optional)</td><td>photo</td><td>file</td><td>JPG/PNG/WEBP</td><td>entity_files</td><td>Categoria <code>internal_piece_photo</code>.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="magazie" class="h5">6. Magazie</h2>
  <ul>
    <li>Rute: <code>/magazie/stoc</code>, <code>/magazie/receptie</code>, <code>/magazie/stoc/{id}</code>.</li>
    <li>DB: <code>magazie_items</code>, <code>magazie_movements</code>, <code>project_magazie_consumptions</code>.</li>
    <li>Audit: <code>MAGAZIE_IN</code>, <code>MAGAZIE_OUT</code>, <code>MAGAZIE_ITEM_DELETE</code>.</li>
  </ul>

  <h3 class="h6 mt-3">Formular: Filtru stoc (GET)</h3>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Caută</td><td>q</td><td>text</td><td>Optional</td><td>n/a</td><td>Filtru pe WinMentor code sau denumire.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Consum stoc Magazie</h3>
  <p class="text-muted">Ruta: <code>POST /magazie/stoc/{id}/consume</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Bucăți</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>magazie_items.stock_qty</td><td>Scade stocul si creeaza movement OUT.</td></tr>
      <tr><td>Cod proiect</td><td>project_code</td><td>text</td><td>max 64</td><td>projects.code / magazie_movements.project_code</td><td>Daca proiectul nu exista, se creeaza placeholder.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Stergere item Magazie</h3>
  <p class="text-muted">Ruta: <code>POST /magazie/stoc/{id}/delete</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>magazie_items / magazie_movements / project_magazie_consumptions</td><td>Sterge consumurile si miscarile asociate.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Recepție marfa (multi-linie)</h3>
  <p class="text-muted">Ruta: <code>POST /magazie/receptie/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cod WinMentor</td><td>winmentor_code[]</td><td>text</td><td>Obligatoriu, max 64</td><td>magazie_items.winmentor_code</td><td>Agregare pe cod.</td></tr>
      <tr><td>Denumire</td><td>name[]</td><td>text</td><td>Obligatoriu, max 190</td><td>magazie_items.name</td><td>—</td></tr>
      <tr><td>Bucăți</td><td>qty[]</td><td>number</td><td>decimal &gt; 0</td><td>magazie_items.stock_qty</td><td>Unitate implicita = buc.</td></tr>
      <tr><td>Pret/buc</td><td>unit_price[]</td><td>text</td><td>decimal &gt;= 0</td><td>magazie_items.unit_price</td><td>Valoare utilizata la miscari.</td></tr>
      <tr><td>Nota</td><td>note</td><td>text</td><td>max 255</td><td>magazie_movements.note</td><td>Se aplica tuturor liniilor.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="clienti" class="h5">7. Clienti</h2>
  <ul>
    <li>Rute: <code>/clients</code>, <code>/clients/create</code>, <code>/clients/{id}</code>, <code>/clients/{id}/edit</code>.</li>
    <li>DB: <code>clients</code>, <code>client_groups</code>, <code>client_addresses</code>.</li>
    <li>Audit: <code>CLIENT_CREATE</code>, <code>CLIENT_UPDATE</code>, <code>CLIENT_DELETE</code>, <code>CLIENT_GROUP_CREATE</code>, <code>CLIENT_ADDRESS_*</code>.</li>
  </ul>

  <h3 class="h6 mt-3">Formular: Client (creare / editare)</h3>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Tip client *</td><td>type</td><td>select</td><td>PERSOANA_FIZICA / FIRMA</td><td>clients.type</td><td>Daca FIRMA, CUI devine obligatoriu.</td></tr>
      <tr><td>Nume (client/companie) *</td><td>name</td><td>text</td><td>Obligatoriu</td><td>clients.name</td><td>—</td></tr>
      <tr><td>Grup de firme (optional)</td><td>client_group_id</td><td>select</td><td>int (optional)</td><td>clients.client_group_id</td><td>Mutual exclusiv cu client_group_new.</td></tr>
      <tr><td>Grup nou (optional)</td><td>client_group_new</td><td>text</td><td>max 190</td><td>client_groups.name</td><td>Creeaza grup si il asociaza.</td></tr>
      <tr><td>CUI (doar pentru firma) *</td><td>cui</td><td>text</td><td>Obligatoriu la FIRMA</td><td>clients.cui</td><td>—</td></tr>
      <tr><td>Persoana contact</td><td>contact_person</td><td>text</td><td>Optional</td><td>clients.contact_person</td><td>—</td></tr>
      <tr><td>Telefon *</td><td>phone</td><td>text</td><td>Obligatoriu</td><td>clients.phone</td><td>—</td></tr>
      <tr><td>Email *</td><td>email</td><td>text</td><td>Email valid</td><td>clients.email</td><td>Validator::email.</td></tr>
      <tr><td>Adresa livrare *</td><td>address</td><td>textarea</td><td>Obligatoriu</td><td>clients.address</td><td>Se sincronizeaza si in client_addresses (best-effort).</td></tr>
      <tr><td>Note</td><td>notes</td><td>textarea</td><td>Optional</td><td>clients.notes</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Creeaza grup clienti</h3>
  <p class="text-muted">Ruta: <code>POST /clients/groups/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Nume grup</td><td>name</td><td>text</td><td>max 190</td><td>client_groups.name</td><td>Audit CLIENT_GROUP_CREATE.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Adauga adresa</h3>
  <p class="text-muted">Ruta: <code>POST /clients/{id}/addresses/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Eticheta</td><td>label</td><td>text</td><td>Optional</td><td>client_addresses.label</td><td>Ex: Sediu / Santier.</td></tr>
      <tr><td>Adresa *</td><td>address</td><td>text</td><td>Obligatoriu</td><td>client_addresses.address</td><td>—</td></tr>
      <tr><td>Note</td><td>notes</td><td>text</td><td>Optional</td><td>client_addresses.notes</td><td>—</td></tr>
      <tr><td>Seteaza ca implicita</td><td>is_default</td><td>checkbox</td><td>Optional</td><td>client_addresses.is_default</td><td>1 = adresa implicita.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Editeaza adresa</h3>
  <p class="text-muted">Ruta: <code>POST /clients/{id}/addresses/{addrId}/edit</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Eticheta</td><td>label</td><td>text</td><td>Optional</td><td>client_addresses.label</td><td>—</td></tr>
      <tr><td>Adresa *</td><td>address</td><td>textarea</td><td>Obligatoriu</td><td>client_addresses.address</td><td>—</td></tr>
      <tr><td>Note</td><td>notes</td><td>textarea</td><td>Optional</td><td>client_addresses.notes</td><td>—</td></tr>
      <tr><td>Seteaza ca implicita</td><td>is_default</td><td>checkbox</td><td>Optional</td><td>client_addresses.is_default</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formulare: Stergere client / adresa</h3>
  <p class="text-muted">Rute: <code>/clients/{id}/delete</code>, <code>/clients/{id}/addresses/{addrId}/delete</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>clients / client_addresses</td><td>Delete cu restrictii de rol.</td></tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="oferte" class="h5">8. Oferte</h2>
  <ul>
    <li>Rute principale: <code>/offers</code>, <code>/offers/create</code>, <code>/offers/{id}</code>.</li>
    <li>DB: <code>offers</code>, <code>offer_products</code>, <code>offer_product_hpl</code>, <code>offer_product_accessories</code>, <code>offer_work_logs</code>.</li>
    <li>Audit: <code>OFFER_CREATE</code>, <code>OFFER_UPDATE</code>, <code>OFFER_CONVERT</code>, <code>OFFER_PRODUCT_ATTACH</code>.</li>
  </ul>

  <h3 class="h6 mt-3">Formular: Oferta (creare / editare)</h3>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cod</td><td>code</td><td>text</td><td>Auto (nu se editeaza)</td><td>offers.code</td><td>Generat incremental.</td></tr>
      <tr><td>Nume</td><td>name</td><td>text</td><td>Obligatoriu</td><td>offers.name</td><td>Validator::required.</td></tr>
      <tr><td>Status</td><td>status</td><td>select</td><td>ENUM</td><td>offers.status</td><td>DRAFT / TRIMISA / ACCEPTATA / RESPINSA / ANULATA.</td></tr>
      <tr><td>Categorie</td><td>category</td><td>text</td><td>Optional</td><td>offers.category</td><td>—</td></tr>
      <tr><td>Deadline</td><td>due_date</td><td>date</td><td>Optional</td><td>offers.due_date</td><td>—</td></tr>
      <tr><td>Descriere</td><td>description</td><td>textarea</td><td>Optional</td><td>offers.description</td><td>—</td></tr>
      <tr><td>Client (opțional)</td><td>client_id</td><td>select</td><td>int (optional)</td><td>offers.client_id</td><td>Mutual exclusiv cu grup.</td></tr>
      <tr><td>Grup de clienti (opțional)</td><td>client_group_id</td><td>select</td><td>int (optional)</td><td>offers.client_group_id</td><td>Mutual exclusiv cu client.</td></tr>
      <tr><td>Note</td><td>notes</td><td>textarea</td><td>Optional</td><td>offers.notes</td><td>—</td></tr>
      <tr><td>Note tehnice</td><td>technical_notes</td><td>textarea</td><td>Optional</td><td>offers.technical_notes</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Convertire oferta in proiect</h3>
  <p class="text-muted">Ruta: <code>POST /offers/{id}/convert</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>projects, offer_products, project_products</td><td>Rezerva stoc si creeaza proiect.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Produs nou in oferta</h3>
  <p class="text-muted">Ruta: <code>POST /offers/{id}/products/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Denumire</td><td>name</td><td>text</td><td>Obligatoriu</td><td>products.name</td><td>Produs nou + attach la oferta.</td></tr>
      <tr><td>Descriere</td><td>description</td><td>textarea</td><td>Optional</td><td>products.notes</td><td>Salvata ca notes.</td></tr>
      <tr><td>Cod (optional)</td><td>code</td><td>text</td><td>Optional</td><td>products.code</td><td>—</td></tr>
      <tr><td>Cantitate</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>offer_products.qty</td><td>—</td></tr>
      <tr><td>Pret cu discount (lei)</td><td>sale_price</td><td>number</td><td>decimal &gt;= 0</td><td>products.sale_price</td><td>Stocat pe produs.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Actualizare produs oferta</h3>
  <p class="text-muted">Ruta: <code>POST /offers/{id}/products/{opId}/update</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cantitate</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>offer_products.qty</td><td>—</td></tr>
      <tr><td>Unitate</td><td>unit</td><td>text</td><td>Optional</td><td>offer_products.unit</td><td>Default buc.</td></tr>
      <tr><td>Pret cu discount</td><td>sale_price</td><td>number</td><td>decimal &gt;= 0</td><td>products.sale_price</td><td>Afecteaza produsul global.</td></tr>
      <tr><td>Descriere</td><td>description</td><td>textarea</td><td>Optional</td><td>offer_products.notes</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Consum HPL pe produs (oferta)</h3>
  <p class="text-muted">Ruta: <code>POST /offers/{id}/products/{opId}/hpl/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Placa</td><td>board_id</td><td>select</td><td>int &gt;= 1</td><td>offer_product_hpl.board_id</td><td>Select2 cu API /api/hpl/boards/search.</td></tr>
      <tr><td>Cantitate</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>offer_product_hpl.qty</td><td>—</td></tr>
      <tr><td>Mod</td><td>consume_mode</td><td>select</td><td>FULL / HALF</td><td>offer_product_hpl.consume_mode</td><td>HALF => 0.5 placa.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Consum accesorii pe produs (oferta)</h3>
  <p class="text-muted">Ruta: <code>POST /offers/{id}/products/{opId}/accessories/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Accesoriu</td><td>item_id</td><td>select</td><td>int &gt;= 1</td><td>offer_product_accessories.item_id</td><td>Select2 cu /api/magazie/items/search.</td></tr>
      <tr><td>Cantitate</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>offer_product_accessories.qty</td><td>—</td></tr>
      <tr><td>Vizibil pe deviz</td><td>include_in_deviz</td><td>checkbox</td><td>Optional</td><td>offer_product_accessories.include_in_deviz</td><td>1 = apare pe deviz.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Manopera pe produs (oferta)</h3>
  <p class="text-muted">Ruta: <code>POST /offers/{id}/products/{opId}/work/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Tip</td><td>work_type</td><td>select</td><td>CNC / ATELIER</td><td>offer_work_logs.work_type</td><td>Costul orar se ia din app_settings.</td></tr>
      <tr><td>Ore</td><td>hours_estimated</td><td>number</td><td>decimal &gt; 0</td><td>offer_work_logs.hours_estimated</td><td>Necesar &gt; 0.</td></tr>
      <tr><td>Nota</td><td>note</td><td>text</td><td>Optional</td><td>offer_work_logs.note</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formulare de stergere (oferta)</h3>
  <p class="text-muted">Rute: <code>/offers/{id}/products/{opId}/delete</code>, <code>/offers/{id}/products/{opId}/hpl/{hplId}/delete</code>, <code>/offers/{id}/products/{opId}/accessories/{accId}/delete</code>, <code>/offers/{id}/products/{opId}/work/{workId}/delete</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>offer_products / offer_product_hpl / offer_product_accessories / offer_work_logs</td><td>Stergere directa.</td></tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="proiecte" class="h5">9. Proiecte</h2>
  <ul>
    <li>Rute principale: <code>/projects</code>, <code>/projects/create</code>, <code>/projects/{id}</code>.</li>
    <li>DB: <code>projects</code>, <code>project_products</code>, <code>project_magazie_consumptions</code>, <code>project_hpl_consumptions</code>, <code>project_product_hpl_consumptions</code>, <code>project_deliveries</code>, <code>project_delivery_items</code>.</li>
    <li>Audit: <code>PROJECT_CREATE</code>, <code>PROJECT_UPDATE</code>, <code>PROJECT_STATUS_CHANGE</code>, <code>PROJECT_CONSUMPTION_*</code>, <code>PROJECT_PRODUCT_*</code>.</li>
  </ul>

  <h3 class="h6 mt-3">Formular: Proiect (creare)</h3>
  <p class="text-muted">Ruta: <code>POST /projects/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Nume</td><td>name</td><td>text</td><td>Obligatoriu</td><td>projects.name</td><td>Validator::required.</td></tr>
      <tr><td>Status</td><td>status</td><td>select</td><td>Enum valid</td><td>projects.status</td><td>Validat cu lista de statusuri.</td></tr>
      <tr><td>Prioritate</td><td>priority</td><td>number</td><td>int (-100000..100000)</td><td>projects.priority</td><td>—</td></tr>
      <tr><td>Categorie</td><td>category</td><td>text</td><td>Optional</td><td>projects.category</td><td>—</td></tr>
      <tr><td>Deadline</td><td>due_date</td><td>date</td><td>Optional</td><td>projects.due_date</td><td>—</td></tr>
      <tr><td>Descriere</td><td>description</td><td>textarea</td><td>Optional</td><td>projects.description</td><td>—</td></tr>
      <tr><td>Client (optional)</td><td>client_id</td><td>select</td><td>int (optional)</td><td>projects.client_id</td><td>Mutual exclusiv cu grup.</td></tr>
      <tr><td>Grup de clienti (optional)</td><td>client_group_id</td><td>select</td><td>int (optional)</td><td>projects.client_group_id</td><td>Mutual exclusiv cu client.</td></tr>
      <tr><td>Etichete</td><td>labels</td><td>hidden</td><td>Optional</td><td>entity_labels / labels</td><td>CSV, propagate la produse.</td></tr>
      <tr><td>Note</td><td>notes</td><td>textarea</td><td>Optional</td><td>projects.notes</td><td>—</td></tr>
      <tr><td>Note tehnice</td><td>technical_notes</td><td>textarea</td><td>Optional</td><td>projects.technical_notes</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Proiect (editare generala)</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/edit</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cod</td><td>code</td><td>text</td><td>Readonly</td><td>projects.code</td><td>Ignorat la update (se pastreaza codul existent).</td></tr>
      <tr><td>Nume</td><td>name</td><td>text</td><td>Obligatoriu</td><td>projects.name</td><td>—</td></tr>
      <tr><td>Descriere</td><td>description</td><td>textarea</td><td>Optional</td><td>projects.description</td><td>—</td></tr>
      <tr><td>Prioritate</td><td>priority</td><td>number</td><td>int</td><td>projects.priority</td><td>—</td></tr>
      <tr><td>Categorie</td><td>category</td><td>text</td><td>Optional</td><td>projects.category</td><td>—</td></tr>
      <tr><td>Deadline</td><td>due_date</td><td>date</td><td>Optional</td><td>projects.due_date</td><td>—</td></tr>
      <tr><td>Client</td><td>client_id</td><td>select</td><td>int (optional)</td><td>projects.client_id</td><td>Mutual exclusiv cu grup.</td></tr>
      <tr><td>Grup clienti</td><td>client_group_id</td><td>select</td><td>int (optional)</td><td>projects.client_group_id</td><td>Mutual exclusiv cu client.</td></tr>
      <tr><td>Note</td><td>notes</td><td>textarea</td><td>Optional</td><td>projects.notes</td><td>—</td></tr>
      <tr><td>Note tehnice</td><td>technical_notes</td><td>textarea</td><td>Optional</td><td>projects.technical_notes</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Schimba status proiect</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/status</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Status nou</td><td>status</td><td>select</td><td>Enum valid</td><td>projects.status</td><td>Nu accepta acelasi status.</td></tr>
      <tr><td>Nota</td><td>note</td><td>text</td><td>Optional</td><td>audit_log.meta_json</td><td>Nota se logheaza in audit.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formulare: Etichete proiect</h3>
  <p class="text-muted">Rute: <code>/projects/{id}/labels/add</code>, <code>/projects/{id}/labels/{labelId}/remove</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Adauga eticheta</td><td>label_name</td><td>text</td><td>max 64</td><td>labels + entity_labels</td><td>Se propaga la produse.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>Folosit si la remove.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Produs nou in proiect</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Denumire</td><td>name</td><td>text</td><td>Obligatoriu</td><td>products.name</td><td>Creeaza produs si il leaga la proiect.</td></tr>
      <tr><td>Descriere</td><td>description</td><td>textarea</td><td>Optional</td><td>products.notes</td><td>—</td></tr>
      <tr><td>Cod (optional)</td><td>code</td><td>text</td><td>Optional</td><td>products.code</td><td>—</td></tr>
      <tr><td>Cantitate</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>project_products.qty</td><td>—</td></tr>
      <tr><td>Pret cu discount (lei)</td><td>sale_price</td><td>number</td><td>decimal &gt;= 0</td><td>products.sale_price</td><td>Stocat pe produs.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Actualizare produs proiect</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/{ppId}/update</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Denumire</td><td>name</td><td>text</td><td>Obligatoriu</td><td>products.name</td><td>Update produs.</td></tr>
      <tr><td>Descriere</td><td>description</td><td>textarea</td><td>Optional</td><td>products.notes</td><td>—</td></tr>
      <tr><td>Cod</td><td>code</td><td>text</td><td>Optional</td><td>products.code</td><td>—</td></tr>
      <tr><td>Cantitate</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>project_products.qty</td><td>—</td></tr>
      <tr><td>Pret cu discount</td><td>sale_price</td><td>number</td><td>decimal &gt;= 0</td><td>products.sale_price</td><td>—</td></tr>
      <tr><td>Suprafata (hidden)</td><td>surface_mode</td><td>hidden</td><td>Obligatoriu (BOARD/M2)</td><td>project_products.surface_type / surface_value / m2_per_unit</td><td>UI foloseste radio <code>surface_mode_ui_{ppId}</code>.</td></tr>
      <tr><td>Suprafata mp (optional)</td><td>surface_m2</td><td>number</td><td>Obligatoriu daca surface_mode = M2</td><td>project_products.surface_value</td><td>MP per bucata.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Status produs (avizare / livrare)</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/{ppId}/status</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Aviz numar</td><td>aviz_number</td><td>hidden</td><td>Obligatoriu la AVIZAT</td><td>project_products.aviz_number</td><td>Completat din modal.</td></tr>
      <tr><td>Aviz data</td><td>aviz_date</td><td>hidden</td><td>Obligatoriu la AVIZAT</td><td>project_products.aviz_date</td><td>Format zz.ll.aaaa in UI, convertit server.</td></tr>
      <tr><td>Data livrare</td><td>delivery_date</td><td>hidden</td><td>Obligatoriu la LIVRAT*</td><td>project_products.delivery_date</td><td>Setat din modal.</td></tr>
      <tr><td>Cantitate livrata</td><td>delivery_qty</td><td>hidden</td><td>&gt; 0, max rest</td><td>project_products.delivered_qty</td><td>Adaugata la livrat.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Facturare/Livrare produs</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/{ppId}/billing/update</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Firma facturare</td><td>invoice_client_id</td><td>select</td><td>client existent</td><td>project_products.invoice_client_id</td><td>Validat contra clients.</td></tr>
      <tr><td>Adresa livrare</td><td>delivery_address_id</td><td>select</td><td>Adresa apartine clientului</td><td>project_products.delivery_address_id</td><td>Validat contra client_addresses.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Consum HPL pe produs</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/{ppId}/hpl/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Sursa</td><td>source</td><td>radio</td><td>PROJECT / REST</td><td>project_product_hpl_consumptions.source</td><td>REST => is_accounting=0, status AVAILABLE.</td></tr>
      <tr><td>Placa / piesa</td><td>piece_id</td><td>select</td><td>int &gt;= 1</td><td>project_product_hpl_consumptions.stock_piece_id</td><td>Select2 cu /api/hpl/pieces/search.</td></tr>
      <tr><td>Consum</td><td>consume_mode</td><td>hidden</td><td>FULL / HALF</td><td>project_product_hpl_consumptions.consume_mode</td><td>HALF doar pentru FULL din proiect.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Accesorii pe produs (rezervare)</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/{ppId}/magazie/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Accesoriu</td><td>item_id</td><td>select</td><td>int &gt;= 1</td><td>project_magazie_consumptions.item_id</td><td>Mode = RESERVED.</td></tr>
      <tr><td>Cantitate</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>project_magazie_consumptions.qty</td><td>—</td></tr>
      <tr><td>Apare pe deviz</td><td>include_in_deviz_flag</td><td>checkbox</td><td>Optional</td><td>project_magazie_consumptions.include_in_deviz</td><td>include_in_deviz=0 hidden + flag.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Actualizare accesoriu rezervat pe produs</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/{ppId}/magazie/{itemId}/update</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cantitate</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>project_magazie_consumptions.qty</td><td>Numai pentru RESERVED.</td></tr>
      <tr><td>Apare pe deviz</td><td>include_in_deviz_flag</td><td>checkbox</td><td>Optional</td><td>project_magazie_consumptions.include_in_deviz</td><td>include_in_deviz=0 hidden + flag.</td></tr>
      <tr><td>Sursa</td><td>src</td><td>hidden</td><td>DIRECT / PROIECT</td><td>n/a</td><td>Controleaza logica de unallocate.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Renunta accesoriu rezervat pe produs</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/{ppId}/magazie/{itemId}/unallocate</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cantitate</td><td>qty</td><td>hidden</td><td>decimal &gt; 0</td><td>project_magazie_consumptions.qty</td><td>Folosita la de-alocare.</td></tr>
      <tr><td>Sursa</td><td>src</td><td>hidden</td><td>DIRECT / PROIECT</td><td>n/a</td><td>Controleaza logica de unallocate.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Da in consum accesorii rezervate (produs)</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/{ppId}/magazie/consume</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>project_magazie_consumptions + magazie_movements</td><td>Schimba mode la CONSUMED si scade stocul.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Manopera (ore) pe produs</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/hours/create</code> (cu <code>project_product_id</code>)</p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Tip</td><td>work_type</td><td>select</td><td>CNC / ATELIER</td><td>project_work_logs.work_type</td><td>Cost preluat din app_settings.</td></tr>
      <tr><td>Ore estimate</td><td>hours_estimated</td><td>number</td><td>decimal &gt; 0</td><td>project_work_logs.hours_estimated</td><td>Validator::dec.</td></tr>
      <tr><td>Nota</td><td>note</td><td>text</td><td>Optional</td><td>project_work_logs.note</td><td>—</td></tr>
      <tr><td>ID produs proiect</td><td>project_product_id</td><td>hidden</td><td>int</td><td>project_work_logs.project_product_id</td><td>Legatura la piesa.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Observatii produs</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/products/{ppId}/comments/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Mesaj</td><td>comment</td><td>textarea</td><td>Optional</td><td>entity_comments.comment</td><td>Entity: project_products.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Consum Magazie la proiect (tab Consum)</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/consum/magazie/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Accesoriu</td><td>item_id</td><td>select</td><td>int &gt;= 1</td><td>project_magazie_consumptions.item_id</td><td>Select2 cu /api/magazie/items/search.</td></tr>
      <tr><td>Cantitate</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>project_magazie_consumptions.qty</td><td>—</td></tr>
      <tr><td>Mod</td><td>mode</td><td>hidden</td><td>RESERVED</td><td>project_magazie_consumptions.mode</td><td>In tab accesorii se forteaza RESERVED.</td></tr>
      <tr><td>Produs (optional)</td><td>project_product_id</td><td>select</td><td>int (optional)</td><td>project_magazie_consumptions.project_product_id</td><td>Leaga consumul la produs.</td></tr>
      <tr><td>Apare pe deviz</td><td>include_in_deviz_flag</td><td>checkbox</td><td>Optional</td><td>project_magazie_consumptions.include_in_deviz</td><td>include_in_deviz=0 hidden + flag.</td></tr>
      <tr><td>Nota</td><td>note</td><td>text</td><td>Optional</td><td>project_magazie_consumptions.note</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Actualizare consum Magazie (proiect)</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/consum/magazie/{cid}/update</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cant</td><td>qty</td><td>number</td><td>decimal &gt; 0</td><td>project_magazie_consumptions.qty</td><td>—</td></tr>
      <tr><td>Unit</td><td>unit</td><td>text</td><td>Optional</td><td>project_magazie_consumptions.unit</td><td>—</td></tr>
      <tr><td>Mod</td><td>mode</td><td>select</td><td>RESERVED/CONSUMED</td><td>project_magazie_consumptions.mode</td><td>—</td></tr>
      <tr><td>Apare pe deviz</td><td>include_in_deviz_flag</td><td>checkbox</td><td>Optional</td><td>project_magazie_consumptions.include_in_deviz</td><td>include_in_deviz=0 hidden + flag.</td></tr>
      <tr><td>Produs</td><td>project_product_id</td><td>select</td><td>int (optional)</td><td>project_magazie_consumptions.project_product_id</td><td>—</td></tr>
      <tr><td>Nota</td><td>note</td><td>text</td><td>Optional</td><td>project_magazie_consumptions.note</td><td>—</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Consum HPL la proiect (tab Consum)</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/consum/hpl/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Placa HPL</td><td>board_id</td><td>select</td><td>int &gt;= 1</td><td>project_hpl_consumptions.board_id</td><td>Select2 cu /api/hpl/boards/search.</td></tr>
      <tr><td>Dimensiune rest</td><td>offcut_dim</td><td>select</td><td>Format W×H</td><td>project_hpl_consumptions.qty_m2</td><td>Optional; daca este selectat, qty_boards devine bucati rest.</td></tr>
      <tr><td>Placi (buc)</td><td>qty_boards</td><td>number</td><td>int &gt;= 1</td><td>project_hpl_consumptions.qty_boards</td><td>Daca offcut, qty_boards se transforma in qty_m2.</td></tr>
      <tr><td>Mod</td><td>mode</td><td>hidden</td><td>RESERVED</td><td>project_hpl_consumptions.mode</td><td>Poate fi CONSUMED din controller.</td></tr>
      <tr><td>Nota</td><td>note</td><td>text</td><td>Optional</td><td>project_hpl_consumptions.note</td><td>Nota se copiaza in piesele din stoc.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Returnare HPL REST in stoc</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/hpl/pieces/{pieceId}/return</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Nota utilizator</td><td>note_user</td><td>hidden</td><td>Optional</td><td>hpl_stock_pieces.notes</td><td>Setata din modalul "return note".</td></tr>
      <tr><td>consum_tab</td><td>consum_tab</td><td>hidden</td><td>Optional</td><td>n/a</td><td>Folosita la redirect.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>hpl_stock_pieces</td><td>Schimba status la AVAILABLE.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Livrare noua</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/deliveries/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Data livrare</td><td>delivery_date</td><td>date</td><td>Optional</td><td>project_deliveries.delivery_date</td><td>Livrari multiple.</td></tr>
      <tr><td>Nota</td><td>note</td><td>text</td><td>Optional</td><td>project_deliveries.note</td><td>—</td></tr>
      <tr><td>Livrez acum</td><td>delivery_qty[ppId]</td><td>number</td><td>0..rest</td><td>project_delivery_items.qty</td><td>Cantitate per produs avizat.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>project_deliveries / project_delivery_items</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Upload fisier proiect</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/files/upload</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Tip entitate</td><td>entity_type</td><td>select</td><td>Optional</td><td>entity_files.entity_type</td><td>Ex: projects, project_products.</td></tr>
      <tr><td>ID entitate</td><td>entity_id</td><td>select</td><td>int</td><td>entity_files.entity_id</td><td>Daca lipseste, se foloseste project_id.</td></tr>
      <tr><td>Categorie</td><td>category</td><td>text</td><td>Optional</td><td>entity_files.category</td><td>Ex: deviz, bon, cnc.</td></tr>
      <tr><td>Fisier</td><td>file</td><td>file</td><td>max 100MB</td><td>entity_files.*</td><td>Salvat in storage/uploads/files.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Discutii proiect</h3>
  <p class="text-muted">Ruta: <code>POST /projects/{id}/discutii/create</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Mesaj</td><td>comment</td><td>textarea</td><td>Optional</td><td>entity_comments.comment</td><td>Entity: projects.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formulare de stergere (proiect)</h3>
  <p class="text-muted">Rute: <code>/projects/{id}/delete</code>, <code>/projects/{id}/consum/magazie/{cid}/delete</code>, <code>/projects/{id}/consum/hpl/{cid}/delete</code>, <code>/projects/{id}/files/{fileId}/delete</code>, <code>/projects/{id}/hours/{workId}/delete</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>projects / project_magazie_consumptions / project_hpl_consumptions / entity_files / project_work_logs</td><td>Stergere directa (cu audit).</td></tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="produse" class="h5">10. Produse</h2>
  <ul class="mb-0">
    <li>Ruta: <code>/products</code> (listare).</li>
    <li>Scop: vizualizare produse din proiecte, cu status si livrari.</li>
    <li>Nu exista formulare POST in aceasta pagina.</li>
  </ul>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="utilizatori" class="h5">11. Utilizatori</h2>
  <ul>
    <li>Rute: <code>/users</code>, <code>/users/create</code>, <code>/users/{id}/edit</code>, <code>/users/{id}/delete</code>.</li>
    <li>DB: <code>users</code>.</li>
    <li>Audit: <code>USER_CREATE</code>, <code>USER_UPDATE</code>, <code>USER_DELETE</code>.</li>
  </ul>

  <h3 class="h6 mt-3">Formular: Utilizator (creare / editare)</h3>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Email *</td><td>email</td><td>email</td><td>Obligatoriu + email valid + unic</td><td>users.email</td><td>—</td></tr>
      <tr><td>Nume *</td><td>name</td><td>text</td><td>Obligatoriu</td><td>users.name</td><td>—</td></tr>
      <tr><td>Rol *</td><td>role</td><td>select</td><td>Rol valid</td><td>users.role</td><td>ADMIN/MANAGER/GESTIONAR/OPERATOR/VIZUALIZARE.</td></tr>
      <tr><td>Cont activ</td><td>is_active</td><td>checkbox</td><td>Optional</td><td>users.is_active</td><td>Nu poti dezactiva propriul cont.</td></tr>
      <tr><td>Parola</td><td>password</td><td>password</td><td>Min 8 caractere</td><td>users.password_hash</td><td>Obligatoriu la creare.</td></tr>
      <tr><td>Confirmare parola</td><td>password_confirm</td><td>password</td><td>Trebuie sa coincida</td><td>n/a</td><td>Doar validare.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Sterge utilizator</h3>
  <p class="text-muted">Ruta: <code>POST /users/{id}/delete</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>users</td><td>Nu poti sterge propriul cont.</td></tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="sistem" class="h5">12. Sistem</h2>
  <ul>
    <li>Consumuri materiale: <code>/system/consumuri-materiale</code></li>
    <li>Setari costuri: <code>/system/costuri</code></li>
    <li>Setari admin: <code>/system/admin-settings</code></li>
    <li>Update DB: <code>/system/db-update</code></li>
  </ul>

  <h3 class="h6 mt-3">Formular: Setari costuri</h3>
  <p class="text-muted">Ruta: <code>POST /system/costuri</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Cost manopera / ora</td><td>cost_labor_per_hour</td><td>number</td><td>decimal &gt;= 0</td><td>app_settings.cost_labor_per_hour</td><td>Folosit in work logs ATELIER.</td></tr>
      <tr><td>Cost CNC / ora</td><td>cost_cnc_per_hour</td><td>number</td><td>decimal &gt;= 0</td><td>app_settings.cost_cnc_per_hour</td><td>Folosit in work logs CNC.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Consumuri materiale (filtru GET)</h3>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>tab</td><td>tab</td><td>hidden</td><td>hpl / accesorii</td><td>n/a</td><td>Controleaza tab-ul.</td></tr>
      <tr><td>De la</td><td>date_from</td><td>date</td><td>Optional</td><td>n/a</td><td>Filtru perioada.</td></tr>
      <tr><td>Pana la</td><td>date_to</td><td>date</td><td>Optional</td><td>n/a</td><td>Filtru perioada.</td></tr>
      <tr><td>Mod</td><td>mode</td><td>select</td><td>CONSUMED/RESERVED/ALL</td><td>n/a</td><td>Filtru consumuri.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Date firma</h3>
  <p class="text-muted">Ruta: <code>POST /system/admin-settings/company/update</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Nume firmă</td><td>company_name</td><td>text</td><td>Optional</td><td>app_settings.company_name</td><td>—</td></tr>
      <tr><td>CUI</td><td>company_cui</td><td>text</td><td>Optional</td><td>app_settings.company_cui</td><td>—</td></tr>
      <tr><td>Nr. Reg. Com.</td><td>company_reg</td><td>text</td><td>Optional</td><td>app_settings.company_reg</td><td>—</td></tr>
      <tr><td>Adresă firmă</td><td>company_address</td><td>textarea</td><td>Optional</td><td>app_settings.company_address</td><td>—</td></tr>
      <tr><td>Telefon firmă</td><td>company_phone</td><td>text</td><td>Optional</td><td>app_settings.company_phone</td><td>—</td></tr>
      <tr><td>Email firmă</td><td>company_email</td><td>email</td><td>Optional</td><td>app_settings.company_email</td><td>—</td></tr>
      <tr><td>Funcție contact</td><td>company_contact_position</td><td>text</td><td>Optional</td><td>app_settings.company_contact_position</td><td>—</td></tr>
      <tr><td>Persoană contact</td><td>company_contact_name</td><td>text</td><td>Optional</td><td>app_settings.company_contact_name</td><td>—</td></tr>
      <tr><td>Telefon contact</td><td>company_contact_phone</td><td>text</td><td>Optional</td><td>app_settings.company_contact_phone</td><td>—</td></tr>
      <tr><td>Email contact</td><td>company_contact_email</td><td>email</td><td>Optional</td><td>app_settings.company_contact_email</td><td>—</td></tr>
      <tr><td>Logo firmă</td><td>company_logo</td><td>file</td><td>JPG/PNG/WEBP</td><td>app_settings.company_logo_*</td><td>Salvat in <code>storage/uploads/company</code>.</td></tr>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>n/a</td><td>—</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formulare: Snapshot DB</h3>
  <p class="text-muted">Rute: <code>/system/admin-settings/snapshot/create</code> si <code>/system/admin-settings/snapshot/restore</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Creeaza snapshot</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>storage/db_backups</td><td>Genereaza fisier .sql.</td></tr>
      <tr><td>Snapshot</td><td>snapshot</td><td>hidden</td><td>Obligatoriu la restore</td><td>storage/db_backups</td><td>Fisierul selectat.</td></tr>
    </tbody>
  </table>

  <h3 class="h6 mt-3">Formular: Update DB</h3>
  <p class="text-muted">Ruta: <code>POST /system/db-update/run</code></p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Token CSRF</td><td>_csrf</td><td>hidden</td><td>Obligatoriu</td><td>DbMigrations</td><td>Aplica migrari lipsa.</td></tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="audit" class="h5">13. Audit</h2>
  <ul>
    <li>Ruta: <code>/audit</code> (listare), <code>/api/audit/{id}</code> (detalii).</li>
    <li>DB: <code>audit_log</code>.</li>
  </ul>

  <h3 class="h6 mt-3">Formular: Filtrare audit (GET)</h3>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Eticheta UI</th><th>name</th><th>Tip</th><th>Validari</th><th>DB / Structura</th><th>Observatii</th></tr>
    </thead>
    <tbody>
      <tr><td>Utilizator</td><td>user_id</td><td>select</td><td>int (optional)</td><td>audit_log.actor_user_id</td><td>Filtru.</td></tr>
      <tr><td>Actiune</td><td>action</td><td>select</td><td>Optional</td><td>audit_log.action</td><td>Filtru.</td></tr>
      <tr><td>De la</td><td>date_from</td><td>date</td><td>Optional</td><td>audit_log.created_at</td><td>Filtru perioada.</td></tr>
      <tr><td>Pana la</td><td>date_to</td><td>date</td><td>Optional</td><td>audit_log.created_at</td><td>Filtru perioada.</td></tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="api" class="h5">14. API intern</h2>
  <p class="mb-2">Endpoint-uri folosite de Select2, grile si AJAX:</p>
  <table class="table table-sm table-bordered align-middle">
    <thead>
      <tr><th>Ruta</th><th>Metoda</th><th>Parametri</th><th>Raspuns</th><th>Utilizare in UI</th></tr>
    </thead>
    <tbody>
      <tr>
        <td><code>/api/health</code></td>
        <td>GET</td>
        <td>—</td>
        <td><code>{ok: true, env}</code></td>
        <td>Endpoint de verificare.</td>
      </tr>
      <tr>
        <td><code>/api/dashboard/top-colors</code></td>
        <td>GET</td>
        <td><code>q</code> (optional)</td>
        <td><code>{ok, html, count}</code></td>
        <td>Dashboard: cautare culori.</td>
      </tr>
      <tr>
        <td><code>/api/hpl/catalog</code></td>
        <td>GET</td>
        <td><code>q</code>, <code>in_stock</code></td>
        <td><code>{ok, html, count}</code></td>
        <td>Catalog HPL (grid).</td>
      </tr>
      <tr>
        <td><code>/api/finishes/search</code></td>
        <td>GET</td>
        <td><code>q</code> / <code>term</code></td>
        <td><code>{ok, items:[{id,text,thumb}]}</code></td>
        <td>Autocomplete culoare in formular placa.</td>
      </tr>
      <tr>
        <td><code>/api/hpl/boards/search</code></td>
        <td>GET</td>
        <td><code>q</code>, <code>project_id</code>, <code>reserved_only</code></td>
        <td><code>{ok, items:[board...]}</code></td>
        <td>Select2 HPL in oferte / proiecte.</td>
      </tr>
      <tr>
        <td><code>/api/hpl/boards/offcuts</code></td>
        <td>GET</td>
        <td><code>board_id</code></td>
        <td><code>{ok, items:[{dim,qty}]}</code></td>
        <td>Consum HPL (resturi) in proiect.</td>
      </tr>
      <tr>
        <td><code>/api/hpl/pieces/search</code></td>
        <td>GET</td>
        <td><code>q</code>, <code>source</code>, <code>project_id</code></td>
        <td><code>{ok, items:[piece...]}</code></td>
        <td>Alocare HPL pe produs (PROJECT / REST).</td>
      </tr>
      <tr>
        <td><code>/api/magazie/items/search</code></td>
        <td>GET</td>
        <td><code>q</code></td>
        <td><code>{ok, items:[{id,text,unit}]}</code></td>
        <td>Select2 accesorii (oferte/proiecte).</td>
      </tr>
      <tr>
        <td><code>/api/audit/{id}</code></td>
        <td>GET</td>
        <td>—</td>
        <td><code>{ok, row}</code></td>
        <td>Modal detalii audit.</td>
      </tr>
    </tbody>
  </table>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="uploads" class="h5">15. Upload-uri si fisiere</h2>
  <ul>
    <li>Finishes: <code>storage/uploads/finishes</code> (JPG/PNG/WEBP). Servite prin <code>/uploads/finishes/{name}</code> (login).</li>
    <li>Logo firma: <code>storage/uploads/company</code>. Servit public prin <code>/uploads/company/{name}</code>.</li>
    <li>Fisier generic: <code>storage/uploads/files</code>, max 100MB, nume sanitizat. Servit prin <code>/uploads/files/{name}</code> (login).</li>
    <li>Tabela legatura: <code>entity_files</code> (entity_type, entity_id, category, original_name, stored_name).</li>
  </ul>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="db" class="h5">16. Entitati si relatii DB (rezumat)</h2>
  <ul class="mb-0">
    <li><strong>users</strong> ↔ autentificare, roluri si audit (actor_user_id).</li>
    <li><strong>finishes</strong> (tip culoare) + <strong>textures</strong> → folosite in <strong>hpl_boards</strong>.</li>
    <li><strong>hpl_boards</strong> (placi) ↔ <strong>hpl_stock_pieces</strong> (piese).</li>
    <li><strong>hpl_stock_pieces</strong> poate avea <code>project_id</code> (rezervari proiect) si <code>is_accounting=0</code> (piese interne).</li>
    <li><strong>magazie_items</strong> ↔ <strong>magazie_movements</strong> (IN/OUT) ↔ <strong>project_magazie_consumptions</strong>.</li>
    <li><strong>offers</strong> ↔ <strong>offer_products</strong> ↔ <strong>offer_product_hpl</strong>, <strong>offer_product_accessories</strong>, <strong>offer_work_logs</strong>.</li>
    <li><strong>projects</strong> ↔ <strong>project_products</strong> ↔ <strong>project_product_hpl_consumptions</strong>.</li>
    <li><strong>project_hpl_consumptions</strong> + <strong>project_hpl_allocations</strong> (rezervare HPL pe proiect).</li>
    <li><strong>project_deliveries</strong> ↔ <strong>project_delivery_items</strong> (livrari pe produs).</li>
    <li><strong>labels</strong> ↔ <strong>entity_labels</strong> (etichete proiect/produse).</li>
    <li><strong>entity_files</strong> si <strong>entity_comments</strong> (fisiere si discutii).</li>
  </ul>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="fluxuri" class="h5">17. Fluxuri complexe (Mermaid)</h2>

  <h3 class="h6 mt-3">17.1 Oferta → Produse → Conversie in Proiect</h3>
  <pre class="mermaid">
flowchart LR
  A[Oferta noua] --> B[Adauga produs in oferta]
  B --> C[Adauga consum HPL / Accesorii / Manopera]
  C --> D[Converteste oferta in proiect]
  D --> E[Creare projects + project_products]
  D --> F[Rezervare HPL si accesorii]
  D --> G[Legare oferta.converted_project_id]
  </pre>

  <h3 class="h6 mt-3">17.2 Proiect → Consum HPL + Magazie → Returnari → Finalizare</h3>
  <pre class="mermaid">
flowchart LR
  P[Proiect] --> C1[Consum HPL (project_hpl_consumptions)]
  P --> C2[Consum accesorii (project_magazie_consumptions)]
  C1 --> R1[Rezervare placi/piese in hpl_stock_pieces]
  C2 --> R2[Rezervare accesorii in magazie_items]
  R1 --> U1[Alocare pe produse (project_product_hpl_consumptions)]
  R2 --> U2[Consum pe produs / la finalizare]
  U1 --> D1[Debitat -> status CONSUMED]
  U1 --> RET[Returnare REST in stoc]
  D1 --> FIN[Livrare / Finalizare]
  </pre>

  <h3 class="h6 mt-3">17.3 Stoc HPL: Placa → Piese → Mutare / Stergere / Alocare</h3>
  <pre class="mermaid">
flowchart LR
  B[Placa HPL] --> P1[Piesa FULL]
  B --> P2[Piesa OFFCUT]
  P1 --> M[Mutare / schimbare status]
  P2 --> M
  M --> R[Rezervare pe proiect]
  R --> A[Alocare pe produs]
  A --> C[Consum / Debitat]
  P1 --> S[Stergere piesa]
  </pre>
</div>

<div class="card app-card p-3 mb-3">
  <h2 id="legacy" class="h5">18. Rute legacy / compat</h2>
  <ul class="mb-0">
    <li><code>/catalog/materials</code> si <code>/catalog/variants</code> redirectioneaza catre <code>/stock</code>.</li>
    <li><code>/hpl/texturi</code> redirectioneaza catre <code>/hpl/tip-culoare#texturi</code>.</li>
    <li>Modulele vechi Materiale/Variante exista in cod, dar nu mai sunt expuse in meniuri.</li>
  </ul>
</div>

  </div>
</div>

<?php
$content = ob_get_clean();
echo View::render('layout/app', compact('title', 'content'));
?>
