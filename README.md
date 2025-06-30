# Plugin iPay88 VA Gateway untuk WooCommerce

## Deskripsi
Plugin WooCommerce untuk integrasi pembayaran via Virtual Account dengan Payment Gateway "ipay88 Indonesia" yang langsung di halaman checkout WooCommerce. Plugin ini mendukung berbagai metode Virtual Account dari beberapa bank tanpa mengarahkan pelanggan keluar dari halaman checkout website. Nomor Virtual Account akan ditampilkan setelah pesanan dibuat, dan status pesanan akan diperbarui secara otomatis setelah konfirmasi pembayaran.

## Fitur
- Mendukung WooCommerce 9.8, WordPress 6.8, dan PHP 8.0
- Mendukung berbagai metode pembayaran Virtual Account:
  - BCA
  - BNI
  - BRI
  - Mandiri
  - CIMB Niaga
  - Danamon
  - Maybank
  - Permata Bank
- Integrasi seamless dengan API iPay88 (tanpa redirect)
- Menampilkan nomor Virtual Account di halaman checkout setelah Place Order
- Mengarahkan ke halaman Order Complete WooCommerce setelah pembayaran berhasil
- Halaman pengaturan dashboard admin untuk konfigurasi iPay88
- Link Settings ditambahkan di sebelah tombol Deactivate pada halaman plugin
- Format nomor referensi: YYYYMMDD-order_id
- Logging error terintegrasi di WooCommerce System Status

## Instalasi
1. Upload file plugin ke direktori `/wp-content/plugins/`, atau instal plugin melalui halaman plugin WordPress.
2. Aktifkan plugin melalui halaman 'Plugins' di WordPress.
3. Buka menu **iPay88 Settings** di admin WordPress dan masukkan Merchant Code, Merchant Key, dan API URL iPay88 Anda.
4. Aktifkan metode pembayaran Virtual Account yang diinginkan di pengaturan pembayaran WooCommerce.
5. Uji proses checkout untuk memastikan nomor Virtual Account muncul dan berfungsi dengan benar.

## Penggunaan
- Pelanggan memilih metode pembayaran Virtual Account iPay88 yang diinginkan di halaman checkout.
- Setelah melakukan pemesanan, nomor Virtual Account akan ditampilkan di halaman checkout.
- Pelanggan melakukan pembayaran menggunakan nomor Virtual Account tersebut.
- Plugin secara otomatis memperbarui status pesanan setelah menerima konfirmasi pembayaran dari iPay88.

## Dukungan
Untuk dukungan plugin bisa hubungi:
- Email: support@sgnet.co.id

## Lisensi
This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
```

## Catatan Perubahan
### 1.0
- Rilis awal dengan dukungan berbagai metode pembayaran Virtual Account iPay88 dan integrasi seamless.