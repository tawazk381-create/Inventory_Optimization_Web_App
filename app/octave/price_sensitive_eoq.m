% File: app/octave/price_sensitive_eoq.m
% EOQ with price-breaks: price_table is Nx2 matrix [break_quantity, unit_price]
% This naive approach computes EOQ at each price level and returns cheapest total annual cost
function out = price_sensitive_eoq(D, S, holding_rate, price_table)
  if nargin < 4
    error('price_sensitive_eoq needs D,S,holding_rate,price_table');
  end
  best = struct('Q',[],'unit_price',[],'total_cost',Inf);
  for i=1:size(price_table,1)
    q_break = price_table(i,1);
    unit_price = price_table(i,2);
    H = unit_price * holding_rate;
    Q = sqrt((2 * D * S) / H);
    % If Q below break point, consider ordering at break point
    Q = max(Q, q_break);
    annual_order_cost = (D / Q) * S;
    annual_holding_cost = (Q / 2) * H;
    annual_purchase = D * unit_price;
    total = annual_order_cost + annual_holding_cost + annual_purchase;
    if total < best.total_cost
      best.Q = round(Q);
      best.unit_price = unit_price;
      best.total_cost = total;
    end
  end
  out = best;
end
