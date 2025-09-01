% File: app/octave/safety_stock.m
% Computes safety stock using service level z * sigma_LT
% Inputs: z - z-score for service level, sigma_d - std dev of demand per day, lead_time_days
function ss = safety_stock(z, sigma_d, lead_time_days)
  if nargin < 3
    error('safety_stock requires z, sigma_d, lead_time_days');
  end
  ss = round(z .* sigma_d .* sqrt(lead_time_days));
end
