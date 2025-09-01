% File: app/octave/abc_xyz.m
% Simple ABC classification by annual consumption value,
% XYZ classification by variability (coefficient of variation)
% Input: items - struct array with fields: id, annual_usage, unit_cost, demand_series (vector)
% Output: items_out with class_abc and class_xyz
function items_out = abc_xyz(items)
  n = numel(items);
  vals = zeros(n,1);
  for i=1:n
    vals(i) = items(i).annual_usage .* items(i).unit_cost;
  end
  [~, idx] = sort(vals, 'descend');
  cum = cumsum(vals(idx)) / sum(vals);
  class_abc = repmat('C', n, 1);
  for k=1:n
    pos = find(idx == k);
    if cum(pos) <= 0.8
      class_abc(k) = 'A';
    elseif cum(pos) <= 0.95
      class_abc(k) = 'B';
    else
      class_abc(k) = 'C';
    end
  end
  class_xyz = repmat('Z', n, 1);
  for i=1:n
    ds = items(i).demand_series;
    if numel(ds) < 2
      class_xyz(i) = 'Z';
      continue;
    end
    mu = mean(ds);
    sigma = std(ds);
    cov = sigma / max(mu, eps);
    if cov <= 0.3
      class_xyz(i) = 'X';
    elseif cov <= 0.7
      class_xyz(i) = 'Y';
    else
      class_xyz(i) = 'Z';
    end
  end
  items_out = items;
  for i=1:n
    items_out(i).class_abc = class_abc(i);
    items_out(i).class_xyz = class_xyz(i);
  end
end
