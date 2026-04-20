# Sistema de 3 Tipos de Usuários - Resumo

## Execute primeiro o SQL atualizado

Execute o arquivo `update_database_parceiro.sql` no phpMyAdmin para atualizar o banco de dados.

---

## 1. ADMINISTRADOR (`tipo_usuario = 'admin'`)

### Permissões:
✅ **Acesso Total ao Sistema**
- Ver todos os clientes
- Cadastrar novos clientes
- Editar todos os clientes
- Excluir clientes
- Acessar financeiro de todos os clientes
- Gerar relatórios de todos os clientes
- Filtrar clientes
- Gerar todos os documentos PDF
- Gerenciar usuários (criar, editar, excluir)

---

## 2. PARCEIRO (`tipo_usuario = 'parceiro'`)

### Permissões:
✅ **Acesso Restrito aos Seus Clientes**
- Ver **APENAS seus clientes** (cadastrados por ele)
- Cadastrar novos clientes (ficam vinculados a ele)
- Editar **APENAS seus clientes**
- **NÃO** pode excluir clientes
- Acessar financeiro **APENAS dos seus clientes**
- Gerar relatórios **APENAS dos seus clientes**
- Filtrar seus clientes
- Gerar documentos PDF **APENAS dos seus clientes**

### Como funciona:
- Quando um parceiro cadastra um cliente, o campo `usuario_cadastro_id` recebe o ID do parceiro
- O parceiro só vê clientes onde `usuario_cadastro_id = seu_id` ou `usuario_cadastro_id IS NULL` (clientes antigos)
- Não pode acessar clientes de outros parceiros

---

## 3. USUÁRIO (`tipo_usuario = 'usuario'`)

### Permissões:
✅ **Acesso Geral sem Financeiro**
- Ver **TODOS os clientes** do sistema
- Cadastrar novos clientes
- Editar **TODOS os clientes**
- **NÃO** pode excluir clientes
- **NÃO** tem acesso ao financeiro
- Gerar relatórios de todos os clientes
- Filtrar clientes
- Gerar todos os documentos PDF

### Como funciona:
- Vê todos os clientes independente de quem cadastrou
- Não tem o botão "Financeiro" disponível
- Se tentar acessar diretamente a URL do financeiro, é redirecionado para o dashboard

---

## Tabela Comparativa

| Funcionalidade | Administrador | Parceiro | Usuário |
|---|:---:|:---:|:---:|
| Ver todos os clientes | ✅ | ❌ (só seus) | ✅ |
| Cadastrar cliente | ✅ | ✅ | ✅ |
| Editar cliente | ✅ Todos | ✅ Só seus | ✅ Todos |
| Excluir cliente | ✅ | ❌ | ❌ |
| Acessar financeiro | ✅ Todos | ✅ Só seus | ❌ |
| Gerar relatórios | ✅ Todos | ✅ Só seus | ✅ Todos |
| Filtrar clientes | ✅ | ✅ | ✅ |
| Gerar documentos PDF | ✅ Todos | ✅ Só seus | ✅ Todos |
| Gerenciar usuários | ✅ | ❌ | ❌ |

---

## Como Criar Cada Tipo de Usuário

### Via Interface (Recomendado):
1. Faça login como **Administrador**
2. Acesse **Dashboard → Gerenciar Usuários**
3. Clique em **+ Novo Usuário**
4. Selecione o **Tipo de Usuário** desejado:
   - **Usuário** (padrão) - Acesso geral sem financeiro
   - **Parceiro** - Acesso apenas aos seus clientes
   - **Administrador** - Acesso total

### Via SQL Direto:
```sql
-- Criar Administrador
INSERT INTO usuarios (nome, email, senha, is_admin, tipo_usuario) 
VALUES ('Admin', 'admin@email.com', 'HASH_SENHA', 1, 'admin');

-- Criar Parceiro
INSERT INTO usuarios (nome, email, senha, is_admin, tipo_usuario) 
VALUES ('Parceiro', 'parceiro@email.com', 'HASH_SENHA', 0, 'parceiro');

-- Criar Usuário
INSERT INTO usuarios (nome, email, senha, is_admin, tipo_usuario) 
VALUES ('Usuario', 'usuario@email.com', 'HASH_SENHA', 0, 'usuario');
```

---

## Arquivos Modificados

✅ `update_database_parceiro.sql` - SQL atualizado para 3 tipos
✅ `login.php` - Carrega tipo_usuario na sessão
✅ `cadastrar_usuario.php` - Permite selecionar os 3 tipos
✅ `listar_usuarios.php` - Exibe os 3 tipos
✅ `listar_clientes.php` - Filtra por parceiro, oculta financeiro para usuário
✅ `relatorio_clientes.php` - Filtra por parceiro
✅ `editar_cliente.php` - Parceiro só edita seus clientes
✅ `financeiro.php` - Bloqueia acesso de usuário, filtra por parceiro
✅ `verificar_permissao.php` - Verifica permissões por tipo
✅ `cadastrar_cliente.php` - Vincula cliente ao usuário que cadastrou

---

## Testando

1. Execute o SQL no phpMyAdmin
2. Crie um usuário de cada tipo
3. Teste com cada tipo de login:
   - **Admin**: Deve ver tudo
   - **Parceiro**: Deve ver apenas seus clientes + financeiro
   - **Usuário**: Deve ver todos os clientes MAS sem financeiro
