% File: app/octave/reorder_point.m
% Reorder point = lead_time_days * avg_daily_demand + safety_stock
function r = reorder_point(lead_time_days, avg_daily_demand, safety_stock)
  if nargin < 3
    safety_stock = 0;
  end
  r = round(lead_time_days .* avg_daily_demand + safety_stock);
end
