# 🚀 Setup Instructions - SoulSync Registration

## ⚠️ Important: Database Setup Required

Antes de usar o formulário de registro, você precisa inicializar o banco de dados.

### Passo 1: Inicializar o Banco de Dados
Acesse no navegador:
```
http://localhost/modeling/api/setup.php
```

**Esperado:** Você verá uma mensagem como:
```json
{
  "status": "success",
  "message": "Banco de dados inicializado! (8 comandos executados)"
}
```

### Passo 2: Verificar Status (Opcional)
Para confirmar que tudo está funcionando:
```
http://localhost/modeling/api/test.php
```

Deve mostrar:
- `"mysql_connection": "OK"`
- `"database": "EXISTE"`
- `"table_count": 4` (users, profiles, interests, user_interests)

### Passo 3: Usar o Registro
Agora você pode acessar:
```
http://localhost/modeling/src/register.html
```

## 📋 Checklist para Debug

Se ainda tiver problemas:

1. **Verifique o arquivo de log:**
   ```
   c:\xampp\htdocs\Modeling\api\debug.log
   ```

2. **Abra o browser DevTools (F12)** e veja:
   - Aba "Console" para erros de JavaScript
   - Aba "Network" para ver a resposta do servidor

3. **Teste a conexão com `test.php`:**
   - Se disser que DB não existe, execute `setup.php`
   - Se disser que table_count é 0, execute `setup.php` novamente

## Error Codes

| Erro | Solução |
|------|---------|
| "localhost recusou a conexão" | XAMPP não está rodando. Inicie XAMPP e Apache |
| "Database connection failed" | MySQL não iniciado. Inicie MySQL no XAMPP |
| "table_count": 0 | Execute setup.php |
| "Server Error" | Verifique os logs em debug.log |
| Formulário não reenvia após upload | Verifique browser console (F12) |

## 📁 Arquivos Importantes

- `register.html` - Formulário de registro (5 passos)
- `register.php` - Backend que salva dados
- `setup.php` - Inicializa banco de dados
- `test.php` - Testa conexão com banco
- `debug.log` - Log de erros
- `db_connect.php` - Configurações do banco

## ✅ Se tudo estiver funcionando

1. Você consegue preencher o formulário em 5 passos
2. Seleciona pelo menos 3 interesses
3. Clica "START SYNC IN 💖"
4. Vê mensagem "Success! Redirecting..."
5. Redireciona para login.html
