# Changelog

Um bloco por dia, do mais novo para o mais antigo. Formato fixo: `## AAAA-MM-DD` e uma linha `- ` por mudança (a tela do plugin lê este arquivo).

## 2026-10-28
- A coluna Status das listas de segmentos, campanhas, usuários e emails mostra o status como um ícone (play verde, stop vermelho) que abre ao passar o mouse; Ativar e Desativar é o primeiro item do menu da linha
- O badge de status abre dentro da coluna, sem empurrar nem cobrir as outras, e os nomes não quebram de linha
- Campanhas que deixam o contato entrar de novo têm o selo azul Reingresso ao lado do status
- Os ícones do nome do segmento viram selos na coluna Status: Global (um globo), Filtros e os avisos vermelhos de segmento sem atualizar e de montagem lenta, com o número
- A lista de contatos tem a coluna Status com Email, SMS e HSM: verde quando o contato pode receber e vermelho quando está em Não Contatar do canal
- A lista de emails mostra o tipo (Campanha ou Broadcast) e os selos de Teste A/B, Tradução e Rascunho na coluna Status, cada um com a sua cor
- A notificação que aparece na tela (toast) fica escura e legível nos temas Escuro e Solarized Escuro
- Novo histórico de mudanças por dia (este arquivo), em uma aba própria do modal do plugin em Configurações > Plugins
- O plugin ganhou o tile "Mautic Patches" em Configurações > Plugins, já ligado e com o ícone do curativo (versão 0.6.0)

## 2026-10-27
- O item Atualizar de campanhas e segmentos aparece e some conforme o status muda, sem recarregar a página

## 2026-10-26
- Os botões de qualquer modal de confirmação ficam no rodapé
- O botão de adicionar das listas de valores do core fica abaixo da lista

## 2026-10-25
- Os tooltips dos botões dos nós da campanha são removidos só quando os botões entram na página

## 2026-10-24
- Contagens de cada nó da campanha com mais espaço; os tooltips dos botões do nó foram removidos
- Os status das listas de segmentos, campanhas, usuários e emails compartilham a mesma base, com mais testes

## 2026-10-23
- Status de Email como selo, com Ativar/Desativar no menu da linha
- Descrição escondida sob o nome nas listas de segmentos e campanhas
- Lista de segmentos: contagem de contatos em selo cinza/azul e linha "Atualizado em" maior
- Selo de status com fonte de 14px, medido de novo quando a fonte carrega
- Selos de status das listas do Mautic como .patches-badge, gêmeo do selo do n8n
- Os selos de template HSM e SMS passam a ser do plugin n8n-dispatch
- Correção: o tile do plugin acompanha o interruptor Ativo logo após salvar (bug do core)

## 2026-10-22
- Campanha, aba Preview: a ampulheta de um nó abre os contatos que aguardam nele, e o check abre os que concluíram, em modal largo e com busca
- Busca na lista de contatos de todas as tabelas do plugin, no mesmo lugar
- O modal de detalhes do segmento e os modais dos nós têm a mesma largura
- Filtros de data do segmento: mantêm as palavras relativas do core, trocam as em inglês e limpam o resto
- Nova ação de campanha Wait: segura o contato até uma data contada a partir de um campo de data
- Wait: "Recalcular datas" (simulação, aplicar e um comando), ícone de pausa e agendamento também após uma decisão
- Páginas de detalhe: botão "Mais >" mostra e esconde a coluna da direita
- Botões Detalhes do segmento e do contato usam o ícone "i"
- Lista de usuários: selo Ativo/Inativo e Desativar/Ativar no menu da linha

## 2026-10-21
- Formatação de campos de data e hora enquanto se digita
- Campanha, builder: cursor de mira sobre os círculos de conexão, botão de desconectar sobre o círculo com ícone de elo quebrado, linhas e círculos mais escuros no tema claro e arrastar o canvas com o botão esquerdo em um espaço vazio
- Ícone de carregamento, e não o do interruptor girando, enquanto um interruptor de lista trabalha
- Status de segmentos e campanhas como selo informativo, com Ativar/Desativar no menu da linha
- Título da página do segmento como PREFIXO - Nome
- Texto do botão voltar em pt_BR corrigido (Voltar ao %alvo%) e outros textos com marcadores errados
- Notificações fixas na tela e mais duradouras
- Lista de segmentos: o nome sem o alias
- Texto preto no hover vermelho do item Excluir dos menus, nos temas escuros

## 2026-10-20
- Chips de incluindo/excluindo na condição de campanha de Custom Objects
- Nova condição de campanha "Campos de contato": vários critérios em campos de contato e empresa
- Builder: botão de lixeira em cada linha para desconectar, e círculos dos nós acima do nó (70% para fora)
- O nó da condição de campos de contato no canvas diz o que é

## 2026-10-19
- Novo botão Atualizar para campanhas, como o dos segmentos
- O nome do segmento na lista abre um modal de detalhes, com botões no rodapé no visual do Mautic
- Modal de erro de validação do builder de email no visual do Mautic, com os erros em lista
- Modal de detalhes do contato: Editar, Detalhes e Fechar no rodapé
- Temas escuros: pontinhos das ações em massa do cabeçalho da lista legíveis

## 2026-10-16
- Correção: balão de espera duplicado no builder após editar um evento
- Builder e aba Preview: a espera aparece como uma faixa sobre o nó
- Canvas da campanha: arrastar com o botão do meio do mouse
- Faixa de espera: o caminho "não" de uma condição diz "esperar", não "entre"
- Formulário do segmento: botão "Duplicar condição" em cada filtro
- Temas escuros: os detalhes de uma linha expandida da Auditoria eram um bloco branco

## 2026-10-15
- Builder: o modal do evento (ações, condições, decisões) tem a largura do modal de detalhes do contato
- Formulário do segmento: os filtros primeiro e o seletor que adiciona um por último
- Modal do contato: sem segundo espaçamento ao redor da tabela de detalhes

## 2026-10-14
- Chips: botão de copiar, um valor por linha
- Campanha: Prefixo obrigatório (caixa de configuração, seleção no formulário, chave na API, coluna ordenável na lista)
- Origem de contatos da campanha: segmentos chamados PREFIXO - Nome no seletor, no nó do canvas, no filtro da lista, na aba Preview e no título da página

## 2026-10-13
- O nome público do segmento é sempre "PREFIXO - Nome"
- Segmento: "em" / "não em" em campos de texto de contato e empresa, com componente de chips
- Aba Filtros do segmento: valores incluídos e excluídos como selos
- Lista de contatos: ícone girando no botão do modo ao vivo enquanto ele está ligado

## 2026-10-12
- O nome do segmento exige o prefixo (campo dividido e validação no formulário e na API)
- O prefixo do segmento é escolhido de uma lista cadastrada em Configurações > Configurações de segmentos
- O prefixo é uma coluna própria, ordenável no servidor
- O alias do segmento, quando não digitado, é o prefixo e o nome em minúsculas, só letras e números

## 2026-10-10
- Abas de contatos do segmento e da campanha com a tabela da lista de contatos
- Página do segmento: o gráfico de estatísticas fica abaixo dos contatos e o filtro de contatos abaixo do gráfico
- Página do segmento: aba Filtros, a primeira e aberta por padrão, com os filtros que o segmento aplica

## 2026-10-09
- Lista de contatos: a opção Ver e o nome do contato abrem a tabela de detalhes em um modal, e Detalhes abre em nova aba
- Temas escuros: fundo legível nas linhas marcadas como novas

## 2026-10-07
- Página do contato: o bloco Detalhes abre por padrão e um botão esconde ou mostra a coluna da direita (sempre começa escondida)
- Atualizar do segmento: o botão clicado fica travado, com um carregando, até a atualização terminar (rota de status e consulta periódica), com "Atualizando..." na linha "Atualizado em"
- Monitor de desempenho: tempo da API (Server-Timing e log do dia), tela Desempenho, relatório e comandos de diagnóstico
- Tela Desempenho: "Executar diagnóstico" trava o botão e atualiza a tela com as verificações
- O log de desempenho guarda os nomes dos parâmetros da consulta e o tamanho da resposta

## 2026-10-03
- Linhas de tabela mais escuras no tema claro

## 2026-10-02
- Linhas de tabela listradas brancas corrigidas nos temas escuros
- Nova API POST /api/segments/{id}/rebuild: recalcula um segmento agora, em segundo plano
- Item Atualizar no menu da linha da lista de segmentos e no menu da página do segmento, com mensagem curta "O segmento está sendo atualizado"
- Lista de segmentos: mostra quando cada segmento foi atualizado, na língua do usuário
- Temas escuros: hover de Salvar e Fechar / Cancelar sem texto branco sobre branco; colunas brancas seguem o tema
- Os botões dos modais dividem toda a largura do rodapé
- Tema claro: cinza claro no lugar do branco puro e camadas e superfícies mais escuras
- Ícone próprio do plugin no lugar do genérico do Mautic
- Página da campanha: o Preview da jornada vem antes do bloco de estatísticas

## 2026-09-25
- Criação do Mautic Patches: correção de um bug do overlay de "Jump to Event"
- README com o propósito do repositório e suas correções
