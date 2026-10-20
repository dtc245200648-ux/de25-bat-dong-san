# Chứng thư HTTPS demo

Không commit private key. Tạo chứng thư tự ký cục bộ trong thư mục này bằng OpenSSL:

```sh
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout cert.key -out cert.crt \
  -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"
```

Thư mục này được bind mount vào Nginx ở chế độ chỉ đọc. Trình duyệt sẽ cảnh báo vì chứng thư tự ký.
