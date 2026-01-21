# Hotfix v1.0.1 - verificari minimale

Scop: confirmare ca se pot crea Proiect si Oferta fara email/telefon.

## Scenarii manuale

1) Creare client fara telefon/email
- Acceseaza /clients/create
- Completeaza Tip client, Nume, Adresa livrare
- Lasa Telefon si Email goale
- Salveaza
- Rezultat: clientul se creeaza fara erori

2) Creare oferta fara telefon/email
- Acceseaza /offers/create
- Completeaza campurile obligatorii (Nume, Status)
- Selecteaza clientul creat la pasul 1 (sau nu selecta client)
- Lasa Telefon/Email necompletate in client (daca e cazul)
- Salveaza
- Rezultat: oferta se creeaza fara erori

3) Creare proiect fara telefon/email
- Acceseaza /projects/create
- Completeaza campurile obligatorii (Nume, Status)
- Selecteaza clientul creat la pasul 1 (sau nu selecta client)
- Salveaza
- Rezultat: proiectul se creeaza fara erori

4) Compatibilitate
- Creeaza client cu Telefon + Email completate
- Creeaza oferta si proiect folosind acel client
- Rezultat: fluxul functioneaza identic
