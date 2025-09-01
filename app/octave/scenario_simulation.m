% File: app/octave/scenario_simulation.m
% Simple scenario: scale demand series by factor and recompute EOQ/reorder
% Input: items struct array with fields: avg_daily_demand, lead_time_days, unit_cost
% factor: multiplier for demand (e.g., 1.2 for +20%)
function results = scenario_simulation(items, factor)
  if nargin < 2
    factor = 1.0;
  end
  n = numel(items);
  results = struct([]);
  for i=1:n
    D = items(i).avg_daily_demand * 365 * factor;
    S = items(i).order_cost; if isempty(S), S = 50; end
    H = items(i).unit_cost * 0.25;
    qstar = round(sqrt((2 .* D .* S) ./ max(H, 1e-6)));
    rp = round(items(i).lead_time_days * items(i).avg_daily_demand * factor + items(i).safety_stock);
    results(i).item_id = items(i).id;
    results(i).eoq = qstar;
    results(i).reorder_point = rp;
  end
end
