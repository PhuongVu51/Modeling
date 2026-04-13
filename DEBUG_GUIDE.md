# 🔧 Debug Guide - Resolvendo "Invalid Response"

## Passo 1: Teste simples de JSON

Acesse em seu navegador:
```
http://localhost/modeling/api/json_test.php
```

**Esperado:** Você verá algo como:
```json
{
  "status": "success",
  "message": "JSON test OK",
  "timestamp": "2026-04-13 12:59:00",
  "php_version": "7.4.33"
}
```

Se NÃO for JSON válido, significa que há um problema com seu PHP/servidor.

---

## Passo 2: Abra o DevTools (F12)

Passo a passo:
1. Abra `http://localhost/modeling/src/register.html`
2. Pressione **F12** para abrir DevTools
3. Vá para aba **"Console"** (ou "Bảng điều khiển")
4. Preencha o formulário e clique "START SYNC IN 💖"
5. Procure por **"=== Debug Info ==="** no console

---

## Passo 3: Procure por estes logs:

```
=== Debug Info ===
Interests: 1,7,5
Form data keys: [...]
Sending request to ../api/register.php
Response status: 200
Raw response: {"status":"success"...
```

**Se você vir isso, significa que funcionou!**

---

## Passo 4: Se ele disser "Invalid response"

Procure pela linha:
```
Raw response: [algo que não começa com "{"]
```

Por exemplo, se vir:
- `Raw response: <!DOCTYPE html>...` = Erro HTML (banco não existe)
- `Raw response: Parse error...` = Erro PHP (syntax error)
- `Raw response: Warning: ...` = PHP Warning antes do JSON

---

## Passo 5: Se tiver erro de banco

Acesse:
```
http://localhost/modeling/api/setup.php
```

Se vir sucesso, depois acesse:
```
http://localhost/modeling/api/test.php
```

Verifique:
- `"database": "EXISTE"` ✅
- `"table_count": 4` ✅

---

## Passo 6: Teste requisição direta

Abra console (F12) e cole este código:

```javascript
fetch('../api/debug_request.php', {
    method: 'POST',
    body: new FormData(document.getElementById('registerForm'))
})
.then(r => r.json())
.then(d => console.log('DEBUG:', d))
```

Isto mostrará exatamente o que o server está recebendo.

---

## 🆘 Se nada funcionar

Colete estas informações:

1. **Console do navegador (F12):**
   - Copie toda a saída com erro

2. **Página `test.php`:**
   - Screenshot mostrando os status

3. **Arquivo `setup.php`:**
   - Mensagem que apareceu

Compartilhe isso para ajudar a diagnosticar! 🔍
