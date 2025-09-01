% File: app/octave/eoq.m
% Computes EOQ: Q* = sqrt(2 * D * S / H)
% Inputs: D - annual demand, S - order cost per order, H - holding cost per unit per year
function q = eoq(D, S, H)
  if nargin < 3
    error('eoq requires D, S, H');
  end
  if H <= 0
    H = 1e-6;
  end
  q = sqrt((2 .* D .* S) ./ H);
end
