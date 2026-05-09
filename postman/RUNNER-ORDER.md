# Postman Runner Order

Urutan run yang disarankan agar data saling nyambung:

1. `00 - Auth`
   - Login semua role untuk mengisi variable token otomatis.
2. `01 - Me & Dashboard`
   - Validasi token dan akses role dasar.
3. `04 - Inventory`
   - Jalankan `Create Usulan Stok (Gudang)` lalu `Approve Usulan Stok (Pengurus)`.
4. `02 - POS`
   - Jalankan `Checkout`, lalu `Receipt by id_transaksi` (id otomatis disimpan).
5. `03 - Loan & Installment`
   - Ajukan pinjaman, upload angsuran (anggota), approve pinjaman, verify angsuran.
6. `05 - Membership Activation`
   - Aktivasi calon anggota (admin only).
7. `06 - Reports`
   - Cek semua laporan (sales, jurnal, buku besar, neraca, laba rugi, shu).

## Catatan

- Update variable ID di environment kalau seed data kamu beda:
  - `id_anggota`, `id_pengurus`, `id_kasir`, `id_produk_1`, `id_supplier`, `id_pinjaman`, `id_angsuran`, `id_usulan`.
- Untuk request `Upload Angsuran (Anggota)`, isi `bukti_transfer_path` dengan path file gambar lokal kamu.
- Pastikan backend aktif dan database sudah `migrate` + `seed`.

