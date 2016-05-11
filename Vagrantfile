# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|
  config.vm.box = "ubuntu/trusty64"

  if Vagrant.has_plugin?("vagrant-cachier")
    config.cache.scope = :box
    config.cache.enable :apt
  end

  config.vm.network "forwarded_port", guest: 80, host: 8000

  config.vm.provider "virtualbox" do |vb|
    vb.memory = "2048"
  end

  config.vm.provision "ansible" do |ansible|
    ansible.galaxy_role_file = "ansible/requirements.yml"
    ansible.playbook = "ansible/site.yml"
  end
end
