cp -R LICENSE ~/Downloads/Satispay
cp -R README.md ~/Downloads/Satispay
cp -R RELEASENOTES.md ~/Downloads/Satispay
cd ~/Downloads && find . -name ".DS_Store" -delete
zip -r Satispay.zip Satispay -x ".*" -x "__MACOSX"