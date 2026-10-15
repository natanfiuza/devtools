-- Faturamento por cliente no ultimo trimestre
select
    c.id,
    c.nome,
    count(p.id) as pedidos,
    sum(p.total) as faturado,
    case when sum(p.total) > 10000 then 'ouro' else 'prata' end as faixa
from clientes c
left join pedidos p on p.cliente_id = c.id and p.status = 'pago'
where c.ativo = 1
  and p.criado_em >= '2026-07-01'
group by c.id, c.nome
having sum(p.total) > 1000
order by faturado desc
limit 50;

-- Os que nunca compraram
select c.nome, c.email
from clientes c
where not exists (select 1 from pedidos p where p.cliente_id = c.id);
