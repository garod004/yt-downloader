function localizarCadastro() {
    let cpf = document.getElementById("cpf").value;

    if (cpf.trim() === "") {
        alert("Por favor, preencha o campo CPF para realizar a busca.");
        return;
    }

    // Envia o CPF para o script PHP via método POST
    fetch('localizar_cadastro.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'cpf=' + encodeURIComponent(cpf) // Envia os dados como string URL-encoded
    })
    .then(response => response.json()) // Espera uma resposta JSON do PHP
    .then(data => {
        if (data.success) {
            // Preenche os campos do formulário com os dados recebidos
            document.getElementById("nome").value = data.nome;
            document.getElementById("nacionalidade").value = data.nacionalidade;
            document.getElementById("profissao").value = data.profissao;
            document.getElementById("estado-civil").value = data.estado_civil;
            document.getElementById("rg").value = data.rg;
            document.getElementById("cpf").value = data.cpf;
            document.getElementById("endereco").value = data.endereco;
            document.getElementById("cidade").value = data.cidade;
            document.getElementById("uf").value = data.uf;
            document.getElementById("telefone").value = data.telefone;
            document.getElementById("email").value = data.email;
            document.getElementById("observacoes").value = data.observacoes;
            alert("Cadastro localizado com sucesso!");
        } else {
            alert(data.message);
            // Opcional: Limpar campos se a busca falhar
            // document.getElementById("nome").value = ""; 
        }
    })
    .catch(error => {
        console.error('Erro na requisição AJAX:', error);
        alert("Ocorreu um erro na comunicação com o servidor.");
    });
}

      